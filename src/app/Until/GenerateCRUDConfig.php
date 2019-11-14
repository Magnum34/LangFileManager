<?php

namespace Backpack\LangFileManager\app\Until;

use Backpack\LangFileManager\app\Models\CRUDConfig;

/**
 * Class GenerateCRUDConfig
 */
class GenerateCRUDConfig
{

    const PATH_CRUD = '/config/backpack/crud.php';

    private $filename;

    public function __construct()
    {
        $this->filename = base_path().self::PATH_CRUD;
    }

    /**
     * Write database to config CRUD
     * Backup config CRUD :)
     */
    public function write(){
        $content = include($this->filename);
        $crud = new CRUDConfig();
        $crud->config = json_encode($content);
        $crud->save();
    }

    /**
     * Add Language in the config CRUD
     * @param $name
     * @param $abbr - Code (ISO 639-1)
     */
    public function generateUploadFile($name, $abbr){
        $content = include($this->filename);
        if(array_key_exists('locales', $content)){
            foreach($content['locales'] as $key => $language){
                if($key != $abbr){
                    $content['locales'][$abbr] = $name;
                }
            }
        }

        file_put_contents(
            $this->filename ,
            "<?php\nreturn " . var_export($content, true) . "\n?>"
        );

    }

    /**
     * Remove Language in the config crud
     * @param $abbr - Code (ISO 639-1)
     */
    public function generateDestroyLanguageUploadFile($abbr){
        $content = include($this->filename);
        if(array_key_exists('locales', $content)){
            foreach($content['locales'] as $key => $language){
                if($key == $abbr){
                    unset($content['locales'][$abbr]);
                }
            }
        }

        file_put_contents(
            $this->filename ,
            "<?php\nreturn " . var_export($content, true) . "\n?>"
        );
    }

    /**
     * @return string
     */
    public function read(){
        $crud =  CRUDConfig::latest('upload_time')->first();
        return json_decode($crud->config, 1);
    }
}
