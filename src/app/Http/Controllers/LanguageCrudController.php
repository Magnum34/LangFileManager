<?php

namespace Backpack\LangFileManager\app\Http\Controllers;

use Illuminate\Http\Request;
use Backpack\LangFileManager\app\Models\Language;
use Backpack\LangFileManager\app\Services\LangFiles;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\LangFileManager\app\Http\Requests\LanguageRequest;

use Illuminate\Http\File;
use Backpack\LangFileManager\app\Until\GenerateCRUDConfig;

class LanguageCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation {store as traitStore; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation {destroy as traitDestroy; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup()
    {

        $this->crud->setModel("Backpack\LangFileManager\app\Models\Language");
        $this->crud->setRoute(config('backpack.base.route_prefix', 'admin').'/language');
        $this->crud->setEntityNameStrings(trans('backpack::langfilemanager.language'), trans('backpack::langfilemanager.languages'));

    }

    public function setupListOperation()
    {
        $this->crud->setColumns([
            [
                'name' => 'name',
                'label' => trans('backpack::langfilemanager.language_name'),
            ],
            [
                'name' => 'active',
                'label' => trans('backpack::langfilemanager.active'),
                'type' => 'boolean',
            ],
            [
                'name' => 'default',
                'label' => trans('backpack::langfilemanager.default'),
                'type' => 'boolean',
            ],
        ]);
        $this->crud->addButton('line', 'translate', 'view', 'langfilemanager::button', 'beginning');
    }

    public function setupCreateOperation()
    {
        $this->crud->setValidation(LanguageRequest::class);
        $this->crud->addField([
            'name' => 'name',
            'label' => trans('backpack::langfilemanager.language_name'),
            'type' => 'text',
        ]);
        $this->crud->addField([
            'name' => 'native',
            'label' => trans('backpack::langfilemanager.native_name'),
            'type' => 'text',
        ]);
        $this->crud->addField([
            'name' => 'abbr',
            'label' => trans('backpack::langfilemanager.code_iso639-1'),
            'type' => 'text',
        ]);
        $this->crud->addField([
            'name' => 'flag',
            'label' => trans('backpack::langfilemanager.flag_image'),
            'type' => 'browse',
        ]);
        $this->crud->addField([
            'name' => 'active',
            'label' => trans('backpack::langfilemanager.active'),
            'type' => 'checkbox',
        ]);
        $this->crud->addField([
            'name' => 'default',
            'label' => trans('backpack::langfilemanager.default'),
            'type' => 'checkbox',
        ]);
    }

    public function setupUpdateOperation()
    {
        return $this->setupCreateOperation();
    }

    public function store()
    {
        $defaultLang = Language::where('default', 1)->first();

        // Copy the default language folder to the new language folder
        \File::copyDirectory(resource_path('lang/'.$defaultLang->abbr), resource_path('lang/'.request()->input('abbr')));


        $generate = new GenerateCRUDConfig();
        $generate->write();
        $generate->generateUploadFile(request()->input('name'), request()->input('abbr'));



        return $this->traitStore();
    }

    /**
     * After delete remove also the language folder.
     *
     * @param int $id
     *
     * @return string
     */
    public function destroy($id)
    {
        $language = Language::find($id);
        $destroyResult = $this->traitDestroy($id);

        if ($destroyResult) {
            \File::deleteDirectory(resource_path('lang/'.$language->abbr));

            $generate = new GenerateCRUDConfig();
            $generate->generateDestroyLanguageUploadFile($language->abbr);

        }

        return $destroyResult;
    }

    public function showTexts(LangFiles $langfile, Language $languages, $lang = '', $file = 'site')
    {
        // SECURITY
        // check if that file isn't forbidden in the config file
        if (in_array($file, config('backpack.langfilemanager.language_ignore'))) {
            abort('403', trans('backpack::langfilemanager.cant_edit_online'));
        }

        if ($lang) {
            $langfile->setLanguage($lang);
        }

        $langfile->setFile($file);
        $this->data['crud'] = $this->crud;
        $this->data['currentFile'] = $file;
        $this->data['currentLang'] = $lang ?: config('app.locale');
        $this->data['currentLangObj'] = Language::where('abbr', '=', $this->data['currentLang'])->first();
        $this->data['browsingLangObj'] = Language::where('abbr', '=', config('app.locale'))->first();
        $this->data['languages'] = $languages->orderBy('name')->where('active', 1)->get();
        $this->data['langFiles'] = $langfile->getlangFiles();
        $this->data['fileArray'] = $langfile->getFileContent();
        $this->data['langfile'] = $langfile;
        $this->data['title'] = trans('backpack::langfilemanager.translations');

        return view('langfilemanager::translations', $this->data);
    }

    public function updateTexts(LangFiles $langfile, Request $request, $lang = '', $file = 'site')
    {
        // SECURITY
        // check if that file isn't forbidden in the config file
        if (in_array($file, config('backpack.langfilemanager.language_ignore'))) {
            abort('403', trans('backpack::langfilemanager.cant_edit_online'));
        }

        $message = trans('error.error_general');
        $status = false;

        if ($lang) {
            $langfile->setLanguage($lang);
        }

        $langfile->setFile($file);

        $fields = $langfile->testFields($request->all());
        if (empty($fields)) {
            if ($langfile->setFileContent($request->all())) {
                \Alert::success(trans('backpack::langfilemanager.saved'))->flash();
                $status = true;
            }
        } else {
            $message = trans('admin.language.fields_required');
            \Alert::error(trans('backpack::langfilemanager.please_fill_all_fields'))->flash();
        }

        return redirect()->back();
    }
}
