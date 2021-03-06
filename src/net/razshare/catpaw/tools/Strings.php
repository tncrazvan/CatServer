<?php
namespace net\razshare\catpaw\tools;

use net\razshare\asciitable\AsciiTable;

abstract class Strings{
    const DATE_FORMAT = "D j M Y G:i:s T";
    const PATTERN_DOUBLE_SLASH = "/\\/\\//";
    const PATTERN_JS_ESCAPE_LEFT_START = "<\\s*(?=script)";
    const PATTERN_JS_ESCAPE_LEFT_END = "<\\s*\\/\\s*(?=script)";
    const PATTERN_JS_ESCAPE_RIGHT_START1 = "(?<=\\&lt\\;script)\\s*>";
    const PATTERN_JS_ESCAPE_RIGHT_START2 = "(?<=\\&lt\\;script).*\\s*>";
    const PATTERN_JS_ESCAPE_RIGHT_END = "(?<=&lt;\\/script)>";


    public static function readLineSilent(string $prompt):?string{
        if (preg_match('/^win/i', PHP_OS)) {
            $vbscript = sys_get_temp_dir() . 'prompt_password.vbs';
            file_put_contents(
              $vbscript, 'wscript.echo(InputBox("'
              . addslashes($prompt)
              . '", "", "password here"))');
            $command = "cscript //nologo " . escapeshellarg($vbscript);
            $password = rtrim(shell_exec($command));
            unlink($vbscript);
            return $password;
          } else {
            $command = "/usr/bin/env bash -c 'echo OK'";
            if (rtrim(shell_exec($command)) !== 'OK') {
              trigger_error("Can't invoke bash");
              return null;
            }
            $command = "/usr/bin/env bash -c 'read -s -p \""
              . addslashes($prompt)
              . "\" mypassword && echo \$mypassword'";
            $password = rtrim(shell_exec($command));
            echo "\n";
            return $password;
          }
          return null;
    }


    /**
     * @param data The data to encode.
     * @param level The level of compression. Can be given as 0 for no compression 
     * up to 9 for maximum compression. If not given, the default compression level will 
     * be the default compression level of the zlib library.
     * @param encodingMode The encoding mode. Can be FORCE_GZIP (the default) or FORCE_DEFLATE.
     * Prior to PHP 5.4.0, using FORCE_DEFLATE results in a standard zlib deflated string 
     * (inclusive zlib headers) after a gzip file header but without the trailing crc32 checksum.
     * In PHP 5.4.0 and later, FORCE_DEFLATE generates RFC 1950 compliant output, consisting of 
     * a zlib header, the deflated data, and an Adler checksum.
     * @return string The encoded string, or FALSE if an error occurred.
     */
    public static function compress(&$type,string &$data,array &$order=["deflate","gzip"], array &$accepted=null):bool{
        $max = count($order);
        if($accepted === null){
            $type = "deflate";
            $data = gzdeflate($data);
            return false;
        }else{
            $len = count($order);
            for($i=0;$i<$len;$i++){
                if(in_array($order[$i],$accepted)){
                    $type = $order[$i];
                    switch($order[$i]){
                        case "deflate":
                            $data = gzdeflate($data);
                            break;
                        case "gzip":
                            $data = gzcompress($data);
                            break;
                        default:
                         return false;
                    }
                    return true;
                }
            }
            return false;
        }
    }

    /**
     * Escape javascript tags from an HTML string.
     * @param content the input string.
     * @return string the escaped string.
     */
    public static function escapeJs(string &$content):string{
        return 
        preg_replace(self::PATTERN_JS_ESCAPE_LEFT_START, "&lt;", 
            preg_replace(self::PATTERN_JS_ESCAPE_LEFT_END, "&lt;/", 
                preg_replace(self::PATTERN_JS_ESCAPE_RIGHT_END, "&gt;", 
                    preg_replace(self::PATTERN_JS_ESCAPE_RIGHT_START1,"&gt;",
                        preg_replace(self::PATTERN_JS_ESCAPE_RIGHT_START2,"&gt;",$content)
                    )
                )
            )
        );
    }

    /**
     * Print an array as an ascii table (recursively).
     * @param input the input array.
     * @param lineCounter if true a number will be visible for each line inside the ascii table.
     * @param intercept intercept the main table and each subtable.<br />
     * This closure will be passed 2 parameters: the AsciiTable and the current depth level.
     * @param lvl the depth level will start counting from this value on.
     * @return string the resulting ascii table.
     */
    public static function tableFromArray(array &$input,bool $lineCounter=false,\Closure $intercept=null,int $lvl = 0):string{
        $table = new AsciiTable();
        if($intercept !== null) $intercept($table,$lvl);
        $table->add("Key","Value");
        foreach($input as $key => &$item){
            if(\is_array($item)){
                $table->add($key,self::tableFromArray($item,$lineCounter,$intercept,$lvl+1));
                continue;
            }else if(\is_object($item)){
                $table->add($key,\get_class($item));
                continue;
            }
            $table->add($key,$item);
        }
            
        return $table->toString($lineCounter);
    }

    /**
     * Generate a universally unique identifier
     * @return string the uuid.
     */
    public static function uuid():string{
        return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        // 32 bits for "time_low"
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

        // 16 bits for "time_mid"
        mt_rand( 0, 0xffff ),

        // 16 bits for "time_hi_and_version",
        // four most significant bits holds version number 4
        mt_rand( 0, 0x0fff ) | 0x4000,

        // 16 bits, 8 bits for "clk_seq_hi_res",
        // 8 bits for "clk_seq_low",
        // two most significant bits holds zero and one for variant DCE1.1
        mt_rand( 0, 0x3fff ) | 0x8000,

        // 48 bits for "node"
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
    }
}
