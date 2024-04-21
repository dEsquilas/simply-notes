<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Http\Request;

class ImportController extends Controller
{

    public function import(Request $request)
    {

        $dir = base_path() . '/toImport/';
        $files = glob($dir . '*.html');

        //$files = [base_path() . '/toImport/Animal.html'];

        Note::where('notebook_id', 4)->delete();


        foreach ($files as $file) {
            $fileContent = file_get_contents($file);
            $this->parseFile($fileContent, $dir);
        }

    }

    private function parseFile($fileContent, $dir)
    {


        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($fileContent);
        $xpath = new \DOMXPath($doc);
        $divs = $xpath->query("/html/body/div");

        $metas = $doc->getElementsByTagName('meta');
        $divs = $doc->getElementsByTagName('div');


        $created = '';
        $updated = '';

        $bodyContent = '';

        foreach ($metas as $meta) {
            if ($meta->getAttribute('itemprop') === 'created') {
                $created = $meta->getAttribute('content');
            }
            if ($meta->getAttribute('itemprop') === 'updated') {
                $updated = $meta->getAttribute('content');
            }
        }

        $title = '';
        $h1 = $doc->getElementsByTagName('h1')->item(0);
        if ($h1) {
            $title = trim(str_replace(PHP_EOL, "", $h1->nodeValue));
        }

        $xpath = new \DOMXPath($doc);

        // to remove
        $icons = $xpath->query('//icons');
        $metas = $xpath->query('//meta');
        $noteAttributes = $xpath->query('//note-attributes');
        $h1 = $xpath->query('//h1');

        $toDelete = array_merge(iterator_to_array($icons), iterator_to_array($metas), iterator_to_array($noteAttributes), iterator_to_array($h1));

        foreach ($toDelete as $item) {
            $item->parentNode->removeChild($item);
            // Remove the icon element
        }

        // replace divs with p
        $divs = $xpath->query('//div[@class="para"]');
        foreach ($divs as $div) {
            $p = $doc->createElement('p');
            while ($div->childNodes->length > 0) {
                $p->appendChild($div->childNodes->item(0));
            }
            $div->parentNode->replaceChild($p, $div);
        }
        // replace span style="font-size: 24px" with class ql-size-large
        $spans = $xpath->query('//span[@style="font-size: 24px;"]');
        foreach ($spans as $span) {
            $span->setAttribute('class', 'ql-size-large');
            $span->removeAttribute('style');
            $span->removeAttribute('data-fontsize');
        }


        $uls = $xpath->query('//ul');

        foreach($uls as $ul){

            $count = $this->countParentNode($ul, 'ul') + $this->countParentNode($ul, 'ol');
            if($count > 0){
                $lis = $xpath->query('.//li', $ul);
                foreach($lis as $li){
                    $li->setAttribute('class', 'ql-indent-' . ($count));
                }
            }

        }

        foreach ($uls as $ul) {

            // count the UL
            $count = $this->countParentNode($ul, 'ul') + $this->countParentNode($ul, 'ol');
            // if counter greater than 1, remove the ul but keep the lis with the ident count
            if ($count > 0) {

                /*$lis = $xpath->query('.//li', $ul);
                foreach ($lis as $li) {
                    $li->setAttribute('class', 'ql-indent-' . ($count));
                }*/

                // move the lis to the parent node in the position of the ol
                while ($ul->childNodes->length > 0) {
                    $ul->parentNode->insertBefore($ul->childNodes->item(0), $ul);
                }
            }

            $lis = $xpath->query('.//li', $ul);
            foreach ($lis as $li) {
                $li->setAttribute('data-list', 'bullet');
                $li->nodeValue = $li->textContent;
            }

            $ol = $doc->createElement('ol');

            // Move all children of ul to ol
            while ($ul->childNodes->length > 0) {
                $ol->appendChild($ul->childNodes->item(0));
            }

            // Replace ul with ol in the parent node
            if ($ul && $ul->parentNode)
                $ul->parentNode->replaceChild($ol, $ul);
        }

        // remove the empty ols

        $ols = $xpath->query('//ol');
        foreach ($ols as $ol) {
            if ($ol->childNodes->length === 0) {
                $ol->parentNode->removeChild($ol);
            }
        }

        // delete the ol that has a ol as parent but keep the lis
        /*$ols = $xpath->query('//ol');


        $lis = $xpath->query('//li');
        foreach ($lis as $li) {
            if($li->exists)

        }*/


        // search the images and replace the src for the base 64
        $imgs = $xpath->query('//img');
        foreach ($imgs as $img) {
            $src = str_replace("\\", "/", $img->getAttribute('src'));
            if (strpos($src, '://') !== false) { // if the src is a link, continue
                continue;
            }
            $type = pathinfo($src, PATHINFO_EXTENSION);
            $data = file_get_contents($dir . $src);
            $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
            $img->setAttribute('src', $base64);
        }

        // foreach td in tr, set the data-row value
        $trs = $xpath->query('//tr');
        foreach ($trs as $k => $tr) {
            $tds = $xpath->query('.//td', $tr);
            foreach ($tds as $td) {
                $td->setAttribute('data-row', 'row-' . $k);
            }
        }

        //remove all divs and p in the td elements and just keep the text
        $tds = $xpath->query('//td');
        $tdData = [];

        // First collect all the td elements and their text content
        foreach ($tds as $td) {
            if($td) {
                $tdData[] = ['element' => $td, 'text' => $td->textContent];
            }
        }

        // Then iterate over the collected data to modify the nodeValue
        foreach ($tdData as $data) {
            $data['element']->nodeValue = $data['text'];
        }

        // remove all td attribues
        $tds = $xpath->query('//td');
        foreach ($tds as $td) {
            $td->removeAttribute('style');
            $td->removeAttribute('width');
            $td->removeAttribute('height');
            $td->removeAttribute('valign');
            $td->removeAttribute('align');
            $td->removeAttribute('data-colwidth');
        }

        // remove all tr attribues
        $trs = $xpath->query('//tr');
        foreach ($trs as $tr) {
            $tr->removeAttribute('style');
            $tr->removeAttribute('width');
            $tr->removeAttribute('height');
            $tr->removeAttribute('valign');
            $tr->removeAttribute('align');
            $tr->removeAttribute('data-colwidth');
        }


        // remove all table attributes
        $tds = $xpath->query('//table');
        foreach ($tds as $td) {
            $td->removeAttribute('style');
            $td->removeAttribute('width');
            $td->removeAttribute('height');
            $td->removeAttribute('valign');
            $td->removeAttribute('align');
            $td->removeAttribute('data-colwidth');
        }

        // remove en-table tag but not it content
        $enTables = $xpath->query('//en-table');
        foreach ($enTables as $enTable) {
            while ($enTable->childNodes->length > 0) {
                $enTable->parentNode->insertBefore($enTable->childNodes->item(0), $enTable);
            }
            $enTable->parentNode->removeChild($enTable);
        }

        // find if tables has a div as parent and delete the parent but not the table
        $tables = $xpath->query('//table');
        foreach ($tables as $table) {
            if ($table->parentNode->nodeName === 'div') {
                $div = $table->parentNode;
                while ($div->childNodes->length > 0) {
                    $div->parentNode->insertBefore($div->childNodes->item(0), $div);
                }
                $div->parentNode->removeChild($div);
            }
        }

        // get the body content
        $enNotes = $xpath->query('//en-note[@class="peso" and @style="white-space: inherit;"]');
        foreach ($enNotes as $enNote) {
            foreach ($enNote->childNodes as $childNode) {
                // Iterate over the child nodes of the en-note element
                $html = $doc->saveHTML($childNode);
                if (strpos($html, '<') === false) {
                    continue;
                }
                $bodyContent .= $html;
            }
        }

        $bodyContent = preg_replace("/\s+/", " ", $bodyContent);
        $bodyContent = str_replace("\n", "", $bodyContent);

        // check if created and updated are correct dates
        if ($created === '') {
            $created = date('Y-m-d H:i:s');
        }
        if ($updated === '') {
            $updated = date('Y-m-d H:i:s');
        }

        $note = new Note();
        $note->title = $title;
        $note->content = $bodyContent;
        $note->created_at = $created;
        $note->updated_at = $updated;
        $note->notebook_id = 4;
        $note->save();

    }

    private function countParentNode($node, $nodeName = 'ol')
    {
        $count = 0;
        while ($node->parentNode) {
            if ($node->parentNode->nodeName === $nodeName) {
                $count++;
            }
            $node = $node->parentNode;
        }
        return $count;
    }

}
