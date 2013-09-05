<?php
/**
 * Updated by magikMaker
 * @link https://github.com/magikMaker/php-closure
 *
 * New features / updates
 * - PHP 5 compatability (visibility)
 * - add source
 * - set closure.jar file location
 * - set soy compiler file location
 * - set lib location
 *
 * $c = new PhpClosure();
 * $c->addSource($js_file_contents)                   // new
 *   ->libDir('/lib/')                                // new
 *   ->closureCompiler('/lib/closure-compiler.jar')   // new
 *   ->soyCompiler('/lib/soy-to-js-src-compiler.jar') // new
 *   ->advancedMode()
 *   ->cacheDir('/cache/')
 *   ->localCompile()
 *   ->write();
 *
 * If you only set the libDir, the script assumes the files
 * 'closure-compiler.jar' and 'soy-to-js-src-compiler.jar' to reside in that
 * folder.
 *
 * You can still use the define('LIB_DIR', getcwd() . 'lib/'); method as
 * described below. If you do that, there's no need to set the compilers.
 *
 * ---
 *
 * Copyright 2012 Alex Kennberg (https://github.com/kennberg/php-closure)
 * Extended under the same license: Apache License 2.0.
 *
 * New features:
 * - Compile locally using Google Closure Compiler
 * - Support for Google Closure Templates and Soy-To-Js Compiler
 * - Add directories with source files.
 *
 * Original notice:
 * Copyright 2010 Daniel Pupius (http://code.google.com/p/php-closure/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * ---
 *
 * PHP wrapper for the Google Closure JS Compiler web service.
 *
 * Handles caching and recompilation of sources.  A recompilation will occur
 * whenever a source file, or the script calling the compiler, changes.
 *
 * The class will handle the Last-Modified and 304 redirects for you, but if
 * you should set Cache-Control headers appropriate for your needs.
 *
 * Example usage:
 *
 * define('LIB_DIR', getcwd() . 'lib/');
 *
 * include(LIB_DIR . 'third-party/php-closure.php');
 *
 * $c = new PhpClosure();
 * $c->add('my-app.js')
 *   ->addDir('/js/') // new
 *   ->add('popup.js')
 *   ->add('popup.soy') // new
 *   ->advancedMode()
 *   ->cacheDir('/tmp/js-cache/')
 *   ->localCompile() // new
 *   ->write();
 *
 * See http://code.google.com/closure/compiler/docs/api-ref.html for more
 * details on the compiler options.
 */
class PhpClosure {
    private $cache_dir           = '';
    private $closure_compiler;
    private $code_url_prefix     = '';
    private $debug               = true;
    private $js_source           = '';
    private $mode                = 'WHITESPACE_ONLY';
    private $local_compile       = false;
    private $lib_dir;
    private $pretty_print        = false;
    private $sources             = array();
    private $soy_compiler;
    private $use_closure_library = false;
    private $warning_level       = 'DEFAULT';

    /**
     * Constructor
     *
     * @access public
     */
    public function __construct(){}

    /**
     * Adds a source file to the list of files to compile.  Files will be
     * concatenated in the order they are added.
     *
     * @access public
     */
    public function add($file) {
        $this->sources[] = $file;
        return $this;
    }

    /**
     *
     */
    public function closureCompiler($compiler){
        $this->
    }

    /**
     * Search directory for source files and add them automatically.
     * Not recursive.
     *
     * @access public
     */
    public function addDir($directory) {
        $iterator = new DirectoryIterator($directory);

        foreach ($iterator as $fileinfo) {
            if (!$fileinfo->isFile()){
                continue;
            }


            // Skip backup files that start with "._".
            if (substr($fileinfo->getFilename(), 0, 2) == '._'){
                continue;
            }

            // Make sure extension is one of 'js' or 'soy'.
            $ext = $fileinfo->getFilename();
            $i   = strrpos($ext, '.');

            if ($i >= 0){
                $ext = substr($ext, $i + 1);
            }

            if ($ext != 'js' && $ext != 'soy'){
                continue;
            }

            $this->add($fileinfo->getPathname());
        }

        return $this;
    }

    /**
     * Add source JavaScript to be compiled
     */
    public function addSource($source){
        $this->js_source .= $source ."\n\n";
        return $this;
    }

    /**
     * Sets the directory where the compilation results should be cached, if
     * not set then caching will be disabled and the compiler will be invoked
     * for every request (NOTE: this will hit ratelimits pretty fast!)
     *
     * @access public
     */
    public function cacheDir($dir) {
        $this->cache_dir = $dir;
        return $this;
    }

    /**
     * Set the directory for the library files
     *
     * @access public
     * @return PhpClosure object
     */
    public function libDir($dir){
        $this->lib_dir = $dir;
        return $this;
    }

    /**
     * Sets whether to use the Closure Library.  i.e. goog.requires will be
     * resolved and the library code made available.
     *
     * @access public
     * @return PhpClosure object
     */
    public function useClosureLibrary() {
        $this->use_closure_library = true;
        return $this;
    }

    /**
     * Sets the URL prefix to use with the Closure Compiler service's code_url
     * parameter.
     *
     * By default PHP-Closure posts the scripts to the compiler service, however,
     * this is subject to a 200000-byte size limit for the whole post request.
     *
     * Using code_url tells the compiler service the URLs of the scripts to
     * fetch.  The file paths added in add() must therefore be relative to this
     * URL.
     *
     * Example usage:
     *
     * $c->add("js/my-app.js")
     *   ->add("js/popup.js")
     *   ->useCodeUrl('http://www.example.com/app/')
     *   ->cacheDir("/tmp/js-cache/")
     *   ->write();
     *
     * This assumes your PHP script is in a directory /app/ and that the JS is in
     * /app/js/ and accessible via HTTP.
     *
     * @access public
     */
    public function useCodeUrl($code_url_prefix) {
        $this->code_url_prefix = $code_url_prefix;
        return $this;
    }

    /**
     * Tells the compiler to pretty print the output.
     *
     * @access public
     */
    public function prettyPrint() {
        $this->pretty_print = true;
        return $this;
    }

    /**
     * Tells the compiler to use local compiler.
     *
     * @access public
     */
    public function localCompile() {
        $this->local_compile = true;
        return $this;
    }

    /**
     * Turns of the debug info.
     * By default statistics, errors and warnings are logged to the console.
     *
     * @access public
     */
    public function hideDebugInfo() {
        $this->debug = false;
        return $this;
    }

    /**
     * Sets the compilation mode to optimize whitespace only.
     *
     * @access public
     */
    public function whitespaceOnly() {
        $this->mode = "WHITESPACE_ONLY";
        return $this;
    }

    /**
     * Sets the compilation mode to simple optimizations.
     *
     * @access public
     */
    public function simpleMode() {
        $this->mode = "SIMPLE_OPTIMIZATIONS";
        return $this;
    }

    /**
     * Sets the compilation mode to advanced optimizations (recommended).
     *
     * @access public
     */
    public function advancedMode() {
        $this->mode = "ADVANCED_OPTIMIZATIONS";
        return $this;
    }

    /**
     * Gets the compilation mode from the URL, set the mode param to
     * 'w', 's' or 'a'.
     *
     * @access public
     */
    public function getModeFromUrl() {
        if ($_GET['mode'] == 's') {
            $this->simpleMode();
        }
        else if ($_GET['mode'] == 'a') {
            $this->advancedMode();
        }
        else {
            $this->whitespaceOnly();
        }

        return $this;
    }

    /**
     * Sets the warning level to QUIET.
     *
     * @access public
     */
    public function quiet() {
        $this->warning_level = "QUIET";
        return $this;
    }

    /**
     * Sets the default warning level.
     *
     * @access public
     */
    public function defaultWarnings() {
        $this->warning_level = "DEFAULT";
        return $this;
    }

    /**
     * Sets the warning level to VERBOSE.
     *
     * @access public
     */
    public function verbose() {
        $this->warning_level = "VERBOSE";
        return $this;
    }

    /**
     * Writes the compiled response.  Reading from either the cache, or
     * invoking a recompile, if necessary.
     *
     * @access public
     */
    public function write() {
        header("Content-Type: text/javascript");

        // No cache directory so just dump the output.
        if (empty($this->cache_dir)) {
            echo $this->compile();
        }
        else {
            $cache_file = $this->getCacheFileName();

            if ($this->isRecompileNeeded($cache_file)) {
                $result = $this->compile();

                if ($result !== false){
                    file_put_contents($cache_file, $result);
                }

                echo $result;
            }
            else {
                // No recompile needed, but see if we can send a 304 to the browser.
                $cache_mtime = filemtime($cache_file);
                $etag        = md5_file($cache_file);
                header("Last-Modified: ".gmdate("D, d M Y H:i:s", $cache_mtime)." GMT");
                header("Etag: $etag");

                if (@strtotime(@$_SERVER['HTTP_IF_MODIFIED_SINCE']) == $cache_mtime ||
                    @trim(@$_SERVER['HTTP_IF_NONE_MATCH']) == $etag) {
                        header("HTTP/1.1 304 Not Modified");
                }
                else {
                    // Read the cache file and send it to the client.
                    echo file_get_contents($cache_file);
                }
            }
        }
    }

    /**
     *
     * @access private
     */
    private function isRecompileNeeded($cache_file) {
        // If there is no cache file, we obviously need to recompile.
        if (!file_exists($cache_file)) {
            return true;
        }

        $cache_mtime = filemtime($cache_file);

        // If the source files are newer than the cache file, recompile.
        foreach ($this->sources as $src) {
          if (filemtime($src) > $cache_mtime) {
              return true;
          }
        }

        // If this script calling the compiler is newer than the cache file,
        // recompile.  Note, this might not be accurate if the file doing the
        // compilation is loaded via an include().
        if (filemtime($_SERVER["SCRIPT_FILENAME"]) > $cache_mtime) {
            return true;
        }

        // Cache is up to date.
        return false;
    }

    /**
     * @access private
     */
    private function exec($cmd, &$stdout, &$stderr) {
        $process = proc_open(
            $cmd,
            array(
                0 => array('pipe', 'r'),
                1 => array('pipe', 'w'),
                2 => array('pipe', 'w')
            ),
            $pipes
        );

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        proc_close($process);
    }

    /**
     * Returns the path to the lib dir
     *
     * @return string the path to the lib dir
     */
    private function getLibDir(){
        $lib_dir = '';

        if(defined('LIB_DIR')){
            $lib_dir = LIB_DIR;
        }
        elseif(!empty($this->lib_dir)){
            $lib_dir = $this->lib_dir;
        }

        return $lib_dir;
    }

    /**
     * @todo lib dir
     * @access private
     */
    private function doLocalCompile() {
        $js_cmd  = 'java -jar ' . $this->getClosureCompilerLocation();
        $js_cmd .= ' --compilation_level ' . $this->mode;
        $js_cmd .= ' --warning_level ' . $this->warning_level;

        if ($this->pretty_print){
            $js_cmd .= ' --formatting pretty_print';
        }

        $soy_cmd         = 'java -jar ' . $this->getSoyCompilerLocation();
        $soy_js_filepath = $this->cache_dir . 'soy.js';
        $soy_cmd        .= " --outputPathFormat $soy_js_filepath";
        $soy_file_count  = 0;

        $this->addSourceToSources();

        foreach ($this->sources as $src) {
            // Determine if this is soy or js by file extension.
            $i = strrpos($src, '.');

            //if ($i > 0 && substr($src, $i + 1) === 'soy') {
            if ($i > 0 && substr($src, $i + 1) === 'soy') {
                $soy_cmd .= " $src";
                $soy_file_count++;
            }
            else {
                $js_cmd .= " --js $src";
            }
        }

        // Run soy compiler.
        if ($soy_file_count > 0) {
          $this->exec($soy_cmd, $stdout, $stderr);

          if (!strlen($stderr)) {
            $js_cmd .= ' --js ' . LIB_DIR . 'third-party/soyutils.js';
            $js_cmd .= " --js $soy_js_filepath";
          }
          else {
            error_log($soy_cmd . "\n");
            error_log($stderr);
            return $this->debug ? false : 'window.console.error(\'Unexpected error\');';
          }
        }

        // Run JS compiler.
        $this->exec($js_cmd, $result, $stderr);

        if (strlen($stderr)) {

          foreach (explode("\n", $stderr) as $line) {
            $line = addslashes(trim($line));

            if (strlen($line))
              $result .= "\r\nwindow.console.error('$line');";
          }

          error_log($js_cmd . "\n");
          error_log($stderr);
          return $this->debug ? false : 'window.console.error(\'Unexpected error\');';
        }

        return $result;
    }

    /*
     * @access private
     */
    private function compile() {
        if ($this->local_compile) {
            return $this->doLocalCompile();
        }

        // Quieten strict notices.
        $code   = $originalSize = $originalGzipSize = $compressedSize = $compressedGzipSize = $compileTime = '';
        $tree   = $this->parseXml($this->makeRequest());
        $result = $tree;

        foreach ($result as $node) {

            switch ($node["tag"]) {

                case "compiledCode":
                    $code = $node["value"];
                break;

                case "warnings":
                    $warnings = $node["value"];
                break;

                case "errors":
                    $errors = $node["value"];
                break;

                case "statistics":

                    foreach ($node["value"] as $stat) {
                        switch ($stat["tag"]) {

                            case "originalSize":
                                $originalSize = $stat["value"];
                            break;

                            case "originalGzipSize":
                                $originalGzipSize = $stat["value"];
                            break;

                            case "compressedSize":
                                $compressedSize = $stat["value"];
                            break;

                            case "compressedGzipSize":
                                $compressedGzipSize = $stat["value"];
                            break;

                            case "compileTime":
                                $compileTime = $stat["value"];
                            break;
                        }
                    }
                break;
            }
        }

        $result = "";

        if ($this->debug) {
            $result = "if(window.console&&window.console.log){\r\n" .
                "window.console.log('Closure Compiler Stats:\\n" .
                "-----------------------\\n" .
                "Original Size: $originalSize\\n" .
                "Original Gzip Size: $originalGzipSize\\n" .
                "Compressed Size: $compressedSize\\n" .
                "Compressed Gzip Size: $compressedGzipSize\\n" .
                "Compile Time: $compileTime\\n" .
                "Generated: " . Date("Y/m/d H:i:s T") . "');\r\n";

            if (isset($errors)) {
                $result .= $this->printWarnings($errors, "error");
            }

            if (isset($warnings)) {
                $result .= $this->printWarnings($warnings, "warn");
            }

            $result .= "}\r\n\r\n";
        }

        $result .= "$code \r\n";

        return $result;
    }

    /**
     * @access private
     */
	private function printWarnings($warnings, $level="log") {
        $result = "";

        foreach ($warnings as $warning) {
            $desc    = addslashes($warning["value"]);
            $type    = $warning["attributes"]["type"];
            $lineno  = $warning["attributes"]["lineno"];
            $charno  = $warning["attributes"]["charno"];
            $line    = addslashes($warning["attributes"]["line"]);
            $result .= "window.console.$level('$type: $desc\\nLine: $lineno\\nChar: $charno\\nLine: $line');\r\n";
        }

        return $result;
    }

    /**
     * @access private
     */
	private function getCacheFileName() {
        return $this->cache_dir . $this->getHash() . ".js";
    }

	/**
     * @access private
     */
	private function getHash() {
        return md5(implode(",", $this->sources) . "-" .
            $this->mode . "-" .
            $this->warning_level . "-" .
            $this->use_closure_library . "-" .
            $this->pretty_print . "-" .
            $this->local_compile . "-" .
            $this->debug);
    }

    /**
     * @access private
     */
	private function getParams() {
        $params = array();

        foreach ($this->getParamList() as $key => $value) {
            $params[] = preg_replace("/_[0-9]$/", "", $key) . "=" . urlencode($value);
        }

        return implode("&", $params);
    }

    /**
     * @access private
     */
    private function getParamList() {
        $params = array();

        if ($this->code_url_prefix) {
            // Send the URL to each source file instead of the raw source.
            $i = 0;

            foreach($this->sources as $file){
                $params["code_url_$i"] = $this->code_url_prefix . $file;
                $i++;
            }
        }
        else {
            $params["js_code"] = $this->readSources();
        }

        $params["compilation_level"] = $this->mode;
        $params["output_format"]     = "xml";
        $params["warning_level"]     = $this->warning_level;

        if ($this->pretty_print) {
            $params["formatting"] = "pretty_print";
        }

        if ($this->use_closure_library) {
            $params["use_closure_library"] = "true";
        }

        $params["output_info_1"] = "compiled_code";
        $params["output_info_2"] = "statistics";
        $params["output_info_3"] = "warnings";
        $params["output_info_4"] = "errors";
        return $params;
    }

    /**
     * @access private
     */
	private function readSources() {
        $code = "";

        foreach ($this->sources as $src) {
            $code .= file_get_contents($src) . "\n\n";
        }

        return $code;
    }

    /**
     * @todo update connection code
     * @access private
     */
    private function makeRequest() {
        $data    = $this->getParams();
        $referer = @$_SERVER["HTTP_REFERER"] or "";

        $fp = fsockopen("closure-compiler.appspot.com", 80) or die("Unable to open socket");;

        if ($fp) {
            fputs($fp, "POST /compile HTTP/1.1\r\n");
            fputs($fp, "Host: closure-compiler.appspot.com\r\n");
            fputs($fp, "Referer: $referer\r\n");
            fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
            fputs($fp, "Content-length: ". strlen($data) ."\r\n");
            fputs($fp, "Connection: close\r\n\r\n");
            fputs($fp, $data);

            $result = "";

            while (!feof($fp)) {
               $result .= fgets($fp, 128);
            }

            fclose($fp);
        }

        $data = substr($result, (strpos($result, "\r\n\r\n")+4));

        if (false !== strpos(strtolower($result), "transfer-encoding: chunked")) {
            $data = $this->unchunk($data);
        }

        return $data;
    }

    /**
     * @access private
     */
    private function unchunk($data) {
        $fp      = 0;
        $outData = "";

        while ($fp < strlen($data)) {
            $rawnum   = substr($data, $fp, strpos(substr($data, $fp), "\r\n") + 2);
            $num      = hexdec(trim($rawnum));
            $fp      += strlen($rawnum);
            $chunk    = substr($data, $fp, $num);
            $outData .= $chunk;
            $fp       += strlen($chunk);
        }

        return $outData;
    }

	/**
     * @access private
     */
	private function parseXml($data) {
        $xml = new SimpleXMLElement($data);
        return $this->parseXmlHelper($xml);
    }

    /**
     * @access private
     */
    private function parseXmlHelper($xml) {
        $tree = null;

        foreach ($xml->children() as $name => $child) {
            $value = (string)$child;
            $node  = array('tag' => $name, 'value' => count($child->children()) == 0 ? $value : $this->parseXmlHelper($child));

            foreach ($child->attributes() as $attr => $value) {
                $node['attributes'][$attr] = $value[0];
            }

            $tree[] = $node;
        }

        return $tree;
    }

    /**
     * writes the source to a temporary file and adds that file to the sources
     */
    private function addSourceToSources(){
        if(!empty($this->js_source)){
            $dir                  = empty($this->cache_dir) ? dirname(__FILE__) : $this->cache_dir;
            $this->js_source_file = $dir.DIRECTORY_SEPARATOR.mt_rand(10000000, 99999999);
            $this->sources[]      = $this->js_source_file;
            file_put_contents($this->js_source_file, $this->js_source);
        }
    }

    /**
     * Returns the file location of the closure compiler
     *
     * @access private
     * @return string the location of the closure compiler jar file
     */
    private function getClosureCompilerLocation(){
        $closure_location = '';

        if(!empty($this->closure_compiler)){
            $closure_location = $this->closure_compiler;
        }
        else{
            $closure_location = $this->getLibDir().'closure-compiler.jar';
        }

        return $closure_location;
    }

    /**
     * Returns the location of the Soy To JS Src Compiler jar file
     *
     * @access private
     * @return string the file location of the jar file
     */
    private function getSoyCompilerLocation(){
        $soy_location = '';

        if(!empty($this->soy_compiler)){
            $soy_location = $this->soy_compiler;
        }
        else{
            $soy_location = $this->getLibDir().'soy-to-js-src-compiler.jar';
        }

        return $soy_location;
    }

}
