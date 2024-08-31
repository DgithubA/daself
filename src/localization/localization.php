<?php

namespace APP\localization;
use APP\Constants\Constants;

class localization {
    protected string $locale;
    /**
     * @var string[]
     */
    protected array $messages = [];
    private static string $LocalsFolderPath = Constants::LocalFolderPath;
    public function __construct($locale = 'en') {
        $this->setLocale($locale);
    }

    public function setLocale($locale) :void{
        if(!self::localAvailable($locale)) throw new \Exception("No localization `$locale` files found.");
        $this->locale = $locale;
        $this->loadMessages();
    }

    public static function localAvailable(string $local):bool{
        $path = "./$local/";
        return (file_exists($path) and ($files = scandir($path)) !== false and $files !== ['.','..']);
    }

    protected function loadMessages() :void{
        $path = self::$LocalsFolderPath."/{$this->locale}/";
        $this->messages = [];
        $files = scandir($path);
        foreach ($files as $file) {
            if ($file === "." or $file === "..") continue;
            $this->messages = array_merge($this->messages, require $path . $file);
        }
    }

    public function get($key, $replace = []) :string{

        $message = $this->messages;

        foreach (explode('.', $key) as $key) {
            if(empty($key)) continue;
            $message = $message[$key] ?? $key;
        }

        if(!is_string($message)) throw new \Exception("language key `$key` not exist.");

        foreach ($replace as $search => $value) {
            $message = str_replace(":$search", $value, $message);
        }
        foreach (['time'=>date('H:i:s'),'date'=>date('Y/m/d'),'week-day'=>date('l')] as $search => $value) {
            $message = str_replace(":$search", $value, $message);
        }
        return $message;
    }
}