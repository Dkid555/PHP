<?php

class GoogleDriveParse
{

    //TODO ↓ link that parses the file
    public $url;

    //TODO ↓ file IDs are under the <c-wiz> tag, with a specific class, don't know if it will change, similar to generating
    public $cwizClassName; //pmHCK

    //TODO ↓ name of the file with permission lie under the <div> tag, with a specific class, I don’t know if it will change, similar to generation
    public $nameClassName; //KL4NAf


    //TODO ↓ the name of the file with permission lies under the <div> tag, with a specific class, I don’t know if it will change, similar to generation
    // by which to search for id and names
    public function __construct($url, $cwizClassName, $nameClassName)
    {
        $this->url = $url;
        $this->cwizClassName = $cwizClassName;
        $this->nameClassName = $nameClassName;
    }


    //TODO ↓ function for downloading files from the generated link
    /**
     * function for downloading files,
      1) Parsing of the page passed by the user during initialization is called
      2) Next, the download function (GET Request) is called for all found IDs and FileNames ->
      * "https://drive.google.com/uc?id={id}", id = ID
      3) If something goes wrong, it will either throw an error or “The files you are looking for are missing”
     * */
    function folderFilesDownload($fileNamesArray, $filepath): void
    {
        try {
            $files = $this->parseFilesByName($fileNamesArray);
            if (!empty($files)) {
                foreach ($files as $id => $filename) {
                    $this->fileDownloadFromGoogle("https://drive.google.com/uc?id=$id", true, $filepath, $filename, "", "");
                }
            } else {
                echo "\nThe files you are looking for are missing\n";
            }
        } catch (Exception $exception) {
            echo $exception;
        }
    }


    //TODO ↓ function for obtaining information about files located in the public google drive folder
     /**
      * Function for parsing a folder passed via $url
      * $fileNamesContains - an array with keys in the form of part of the file names required for downloading
      * Initially, the page is processed through DOMDocument(), elements are searched for by the c-wiz tag, then
      * according to the cwizClassName class, the necessary elements are found and checked for the presence of elements
      * with the data-target attribute, with the value 'doc' (document) - this is how the <div> with ID is located, then it is searched for
      * <div> with content that contains the file name from $fileNamesContains, then the final array is formed,
      * if everything is successful {id => file}
      * */
    function parseFilesByName($fileNamesContains): array
    {
        $DOM = new DOMDocument();
        $DOM->strictErrorChecking = false;
        $http = file_get_contents($this->url);
        @$DOM->loadHTML($http);
        $cwiz = $DOM->getElementsByTagName("c-wiz");//data-id
        $cwiz = $this->getElementsByClass($cwiz, $this->cwizClassName);
        $files = [];
        $written = [];
        foreach ($cwiz as $CW) {
            if (empty($fileNamesContains))
                break;
            $divs = $CW->getElementsByTagName("div");
            $divsWithAttrID = $this->getByAttrubute("data-target", $divs, "doc");
            $file = false;
            if (!empty($divsWithAttrID)) {
                if ($divsWithAttrID[0]->hasAttribute("data-id")) {
                    $id = $divsWithAttrID[0]->getAttribute("data-id");
                    if (array_key_exists($id, $written))
                        continue;
                    $written[$id] = "";
                    foreach ($fileNamesContains as $fileName => $none) {
                        $file = $this->findName($fileName, $divs);
                        if ($file) {
                            unset($fileNamesContains[$fileName]);
                            break;
                        }
                    }
                    if ($file) {
                        $files[$id] = $file;
                        echo "\n$id $file\n";
                    }
                }
            }
        }
        return $files;
    }



    //TODO ↓ search function for DOM elements by class attribute containing $className
     /**
      * Through iteration of all passed elements and the presence of the class attribute, it is checked for a match
      * For beauty (like a method in JS), you can also use “getByAttrubute”, which is further dispensed with :D
      */
    function getElementsByClass($DOM_elements, $className): array
    {
        $nodes = [];
        for ($i = 0; $i < $DOM_elements->length; $i++) {
            $temp = $DOM_elements->item($i);
            if (stripos($temp->getAttribute('class'), $className) !== false) {
                $nodes[] = $temp;
            }
        }

        return $nodes;
    }



    //TODO ↓ function for searching DOM elements by attribute containing $attributeName
     /**
      * Through iteration of all passed elements and the presence of the attribute, it is checked for a match
      */
    function getByAttrubute($attribute, $dom, $attributeName): array
    {
        $arr = [];
        foreach ($dom as $d) {
            if ($d->hasAttribute($attribute) && $d->getAttribute($attribute) == $attributeName) {
                $arr[] = $d;
            }
        }
        return $arr;
    }



    //TODO ↓ function to find the full file name
     /**
      * Function to search for a name containing part of the name passed to $name
      * Searches for <div> by $nameClassName class inside the passed <c-wiz>, from which the id is taken,
      * and then among them checks the texts inside the <div>, looking for a match, if found, then returns string, otherwise false
      */
    function findName($name, $dom): false|string
    {
        $divs = $this->getElementsByClass($dom, $this->nameClassName);
        foreach ($divs as $div) {
            $decoded = mb_convert_encoding($div->textContent, 'ISO-8859-1', 'UTF-8');
            if (str_contains($decoded, $name)) {
                return $decoded;
            }
        }
        return false;
    }



    //TODO ↓ function for downloading files (GET request)
     /** $url - link format https://drive.google.com/uc?id={$id}, $id - file id
      * $ssl - availability of SSL certificate
      * $filePath - folder to download the file
      * $filename - name of the final file with resolution
      * $username - user, not used yet
      * $password - password, not used yet
      */
    function fileDownloadFromGoogle($url, $ssl, $filePath, $filename, $username, $password): void
    {
        if ($ssl) {
            $context = stream_context_create(array(
                'http' => array(
                    'header' => "Authorization: Basic " . base64_encode("$username:$password")
                ),));
        } else
            $context = stream_context_create(array(
                'http' => array(
                    'header' => "Authorization: Basic " . base64_encode("$username:$password")
                ),
                "ssl" => array(
                    "verify_peer" => false,
                    "verify_peer_name" => false,
                ),
            ));
        $file = file_get_contents($url, false, $context);
        $inputFileName = $filePath . $filename;
        file_put_contents($inputFileName, $file);
    }


    //TODO ↓ Method for loading spreadsheet-a from google spreadsheets
     /**
      * The ID is pulled out from the link to the spreadsheet and added to https://docs.google.com/spreadsheets/d/{ID}/export, ID = $id
      */
    function downloadSpreadSheet($filePath, $fileNameXLSX): void
    {
        try {
            $id = explode("/", $this->url);
            $id = $id[count($id) - 1];
            if (!empty($id) && $id != "") {
                $this->fileDownloadFromGoogle("https://docs.google.com/spreadsheets/d/$id/export", true, $filePath, $fileNameXLSX, "", "");
            } else {
                echo "something went wrong, are you sure you have an id at the end of the link?";
            }
        } catch (Exception $e) {
            echo $e;
        }
    }
}
