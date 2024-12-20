<?php

namespace App\Localization;

use App\Constants;

class Lang
{
    /** @var string language code */
    protected string $locale;

    /*** @var string[] */
    protected array $messages = [];

    private static self $instance;
    public static function getInstance(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self(Constants::DefaultLocal);
        }
        return self::$instance;
    }

    public function __construct($locale = 'en')
    {
        $this->setLocale($locale);
    }

    public function setLocale(string $locale): void
    {
        if (!self::localAvailable($locale))
            throw new \Exception("No localization `$locale` files found.");

        $this->locale = $locale;
        $this->loadMessages();
    }

    public static function localAvailable(string $locale): bool
    {
        $path = Constants::LocaleFolderPath . $locale;
        return file_exists($path) &&
            ($files = scandir($path)) !== false &&
            $files !== ['.', '..'];
    }

    protected function loadMessages(): void
    {
        $this->messages = [];
        $path = Constants::LocaleFolderPath . "{$this->locale}/";
        foreach (scandir($path) as $file) {
            if ($file === '.' || $file === '..')
                continue;

            $this->messages = array_merge($this->messages, require $path . $file);
        }
    }

    public function get($key, $replace = []): string
    {
        $message = $this->messages;

        foreach (explode('.', $key) as $key) {
            if (empty($key))
                continue;

            $message = $message[$key] ?? $key;
        }

        if (!is_string($message))
            throw new \Exception("language key `$key` not exist.");

        foreach ($replace as $search => $value) {
            $message = str_replace(":$search", $value, $message);
        }
        foreach (['time' => date('H:i:s'), 'date' => date('Y/m/d'), 'week-day' => date('l')] as $search => $value) {
            $message = str_replace(":$search", $value, $message);
        }
        return $message;
    }
}
