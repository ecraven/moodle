<?php
// This file is part of Moodle - http://moodle.org/
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Import/Export Microsoft Word files library.
 *
 * @package    booktool_wordimport
 * @copyright  2016 Eoin Campbell
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;
// Development: turn on all debug messages and strict warnings.
define('DEBUG_WORDIMPORT', E_ALL);
// @codingStandardsIgnoreLine define('DEBUG_WORDIMPORT', 0);

require_once(dirname(__FILE__).'/xslemulatexslt.inc');

/**
 * Export book HTML into Word-compatible XHTML format
 *
 * Use an XSLT script to do the job, as it is much easier to implement this,
 * and Moodle sites are guaranteed to have an XSLT processor available (I think).
 *
 * @param string $content all HTML content from a book or chapter
 * @return string Word-compatible XHTML text
 */
function booktool_wordimport_export( $content ) {
    global $CFG, $USER, $COURSE, $OUTPUT;

    /*
     * @var string export template with Word-compatible CSS style definitions
    */
    $wordfiletemplate = 'wordfiletemplate.html';
    /*
     * @var string Stylesheet to export XHTML into Word-compatible XHTML
    */
    $exportstylesheet = 'xhtml2wordpass2.xsl';

    // @codingStandardsIgnoreLine debugging(__FUNCTION__ . '($content = "' . str_replace("\n", "", substr($content, 80, 500)) . ' ...")', DEBUG_WORDIMPORT);

    // XHTML template for Word file CSS styles formatting.
    $htmltemplatefilepath = __DIR__ . "/" . $wordfiletemplate;
    $stylesheet = __DIR__ . "/" . $exportstylesheet;

    // Check that XSLT is installed, and the XSLT stylesheet and XHTML template are present.
    if (!class_exists('XSLTProcessor') || !function_exists('xslt_create')) {
        echo $OUTPUT->notification(get_string('xsltunavailable', 'booktool_wordimport'));
        return false;
    } else if (!file_exists($stylesheet)) {
        // Stylesheet to transform Moodle Question XML into Word doesn't exist.
        echo $OUTPUT->notification(get_string('stylesheetunavailable', 'booktool_wordimport', $stylesheet));
        return false;
    }

    // Get a temporary file name for storing the book/chapter XHTML content to transform.
    if (!($tempxmlfilename = tempnam($CFG->tempdir . DIRECTORY_SEPARATOR, "b2w-"))) {
        echo $OUTPUT->notification(get_string('cannotopentempfile', 'booktool_wordimport', basename($tempxmlfilename)));
        return false;
    }
    unlink($tempxmlfilename);
    $tempxhtmlfilename = $CFG->tempdir . DIRECTORY_SEPARATOR . basename($tempxmlfilename, ".tmp") . ".xhtm";

    // Uncomment next line to give XSLT as much memory as possible, to enable larger Word files to be exported.
    // @codingStandardsIgnoreLine raise_memory_limit(MEMORY_HUGE);

    $cleancontent = booktool_wordimport_clean_html_text($content);

    // Set the offset for heading styles, default is h3 becomes Heading 1.
    $heading1styleoffset = '3';
    if (strpos($cleancontent, '<div class="lucimoo">')) {
        $heading1styleoffset = '1';
    }

    // Set parameters for XSLT transformation. Note that we cannot use $arguments though.
    $parameters = array (
        'course_id' => $COURSE->id,
        'course_name' => $COURSE->fullname,
        'author_name' => $USER->firstname . ' ' . $USER->lastname,
        'moodle_country' => $USER->country,
        'moodle_language' => current_language(),
        'moodle_textdirection' => (right_to_left()) ? 'rtl' : 'ltr',
        'moodle_release' => $CFG->release,
        'moodle_url' => $CFG->wwwroot . "/",
        'moodle_username' => $USER->username,
        'debug_flag' => debugging('', DEBUG_WORDIMPORT),
        'heading1stylelevel' => $heading1styleoffset,
        'transformationfailed' => get_string('transformationfailed', 'booktool_wordimport', $exportstylesheet)
    );

    // Write the book contents and the HTML template to a file.
    $xhtmloutput = "<container>\n<container><html xmlns='http://www.w3.org/1999/xhtml'><body>" .
            $cleancontent . "</body></html></container>\n<htmltemplate>\n" .
            file_get_contents($htmltemplatefilepath) . "\n</htmltemplate>\n</container>";
    if ((file_put_contents($tempxhtmlfilename, $xhtmloutput)) == 0) {
        echo $OUTPUT->notification(get_string('cannotwritetotempfile', 'booktool_wordimport', basename($tempxhtmlfilename)));
        return false;
    }

    // Prepare for Pass 2 XSLT transformation (Pass 1 not needed because books, unlike questions, are already HTML.
    $stylesheet = __DIR__ . "/" . $exportstylesheet;
    $xsltproc = xslt_create();
    if (!($xsltoutput = xslt_process($xsltproc, $tempxhtmlfilename, $stylesheet, null, null, $parameters))) {
        echo $OUTPUT->notification(get_string('transformationfailed', 'booktool_wordimport', $stylesheet));
        booktool_wordimport_debug_unlink($tempxhtmlfilename);
        return false;
    }
    booktool_wordimport_debug_unlink($tempxhtmlfilename);

    // Strip out any redundant namespace attributes, which XSLT on Windows seems to add.
    $xsltoutput = str_replace(' xmlns=""', '', $xsltoutput);
    $xsltoutput = str_replace(' xmlns="http://www.w3.org/1999/xhtml"', '', $xsltoutput);
    // Unescape double minuses if they were substituted during CDATA content clean-up.
    $xsltoutput = str_replace("WORDIMPORTMinusMinus", "--", $xsltoutput);

    // Strip off the XML declaration, if present, since Word doesn't like it.
    if (strncasecmp($xsltoutput, "<?xml ", 5) == 0) {
        $content = substr($xsltoutput, strpos($xsltoutput, "\n"));
    } else {
        $content = $xsltoutput;
    }

    return $content;
}   // End booktool_wordimport_export function.

/**
 * Get images and write them as base64 inside the HTML content
 *
 * A string containing the HTML with embedded base64 images is returned
 *
 * @param string $contextid the context ID
 * @param string $filearea filearea: chapter or intro
 * @param string $chapterid the chapter ID (optional)
 * @return string the modified HTML with embedded images
 */
function booktool_wordimport_base64_images($contextid, $filearea, $chapterid = null) {
    // Get the list of files embedded in the book or chapter.
    // Note that this will break on images in the Book Intro section.
    $imagestring = '';
    $fs = get_file_storage();
    if ($filearea == 'intro') {
        $files = $fs->get_area_files($contextid, 'mod_book', $filearea);
    } else {
        $files = $fs->get_area_files($contextid, 'mod_book', $filearea, $chapterid);
    }
    foreach ($files as $fileinfo) {
        // Process image files, converting them into Base64 encoding.
        debugging(__FUNCTION__ . ": $filearea file: " . $fileinfo->get_filename(), DEBUG_WORDIMPORT);
        $fileext = strtolower(pathinfo($fileinfo->get_filename(), PATHINFO_EXTENSION));
        if ($fileext == 'png' or $fileext == 'jpg' or $fileext == 'jpeg' or $fileext == 'gif') {
            $filename = $fileinfo->get_filename();
            $filetype = ($fileext == 'jpg') ? 'jpeg' : $fileext;
            $fileitemid = $fileinfo->get_itemid();
            $filepath = $fileinfo->get_filepath();
            $filedata = $fs->get_file($contextid, 'mod_book', $filearea, $fileitemid, $filepath, $filename);

            if (!$filedata === false) {
                $base64data = base64_encode($filedata->get_content());
                $filedata = 'data:image/' . $filetype . ';base64,' . $base64data;
                // Embed the image name and data into the HTML.
                $imagestring .= '<img title="' . $filename . '" src="' . $filedata . '"/>';
            }
        }
    }

    if ($imagestring != '') {
        return '<div class="ImageFile">' . $imagestring . '</div>';
    }
    return '';
}


/**
 * Clean HTML content
 *
 * A string containing clean XHTML is returned
 *
 * @param string $cdatastring XHTML from inside a CDATA_SECTION in a question text element
 * @return string
 */
function booktool_wordimport_clean_html_text($cdatastring) {
    // Escape double minuses, which cause XSLT processing to fail.
    $cdatastring = str_replace("--", "WORDIMPORTMinusMinus", $cdatastring);

    // Wrap the string in a HTML wrapper, load it into a new DOM document as HTML, but save as XML.
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><html><body>' . $cdatastring . '</body></html>');
    $doc->getElementsByTagName('html')->item(0)->setAttribute('xmlns', 'http://www.w3.org/1999/xhtml');
    $xml = $doc->saveXML();

    $bodystart = stripos($xml, '<body>') + strlen('<body>');
    $bodylength = strripos($xml, '</body>') - $bodystart;
    if ($bodystart || $bodylength) {
        $cleanxhtml = substr($xml, $bodystart, $bodylength);
    } else {
        $cleanxhtml = $cdatastring;
    }

    // Fix up filenames after @@PLUGINFILE@@ to replace URL-encoded characters with ordinary characters.
    $foundpluginfilenames = preg_match_all('~(.*?)<img src="@@PLUGINFILE@@/([^"]*)(.*)~s', $cleanxhtml,
                                $pluginfilematches, PREG_SET_ORDER);
    $nummatches = count($pluginfilematches);
    if ($foundpluginfilenames and $foundpluginfilenames != 0) {
        $urldecodedstring = "";
        // Process the possibly-URL-escaped filename so that it matches the name in the file element.
        for ($i = 0; $i < $nummatches; $i++) {
            // Decode the filename and add the surrounding text.
            $decodedfilename = urldecode($pluginfilematches[$i][2]);
            $urldecodedstring .= $pluginfilematches[$i][1] . '<img src="@@PLUGINFILE@@/' . $decodedfilename .
                                    $pluginfilematches[$i][3];
        }
        $cleanxhtml = $urldecodedstring;
    }

    // Strip soft hyphens (0xAD, or decimal 173).
    $cleanxhtml = preg_replace('/\xad/u', '', $cleanxhtml);

    return $cleanxhtml;
}


/**
 * Delete temporary files if debugging disabled
 *
 * @param string $filename name of file to be deleted
 * @return void
 */
function booktool_wordimport_debug_unlink($filename) {
    if (DEBUG_WORDIMPORT !== DEBUG_DEVELOPER or !(debugging(null, DEBUG_DEVELOPER))) {
        unlink($filename);
    }
}
