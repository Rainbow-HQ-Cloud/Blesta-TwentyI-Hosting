<?php

/**
 * PHPStan bootstrap – stubs Blesta globals so static analysis passes without
 * the framework present. Do NOT load this file in production.
 */

defined('DS') || define('DS', DIRECTORY_SEPARATOR);
defined('ROOTWEBDIR') || define('ROOTWEBDIR', '/');

if (!class_exists('Module')) {
    abstract class Module
    {
        /** @var mixed */
        public $Input = null;
        /** @var mixed */
        public $Record = null;
        /** @var mixed */
        public $view = null;

        public function loadConfig(string $path): void
        {
        }

        /** @param array<int,string> $components */
        public function loadComponents(array $components): void
        {
        }

        public function makeView(string $name, string $type, string $path): object
        {
            return new class () {
                public function set(string $k, mixed $v): void
                {
                }

                public function fetch(): string
                {
                    return '';
                }
            };
        }

        public function getModuleRow(int $moduleRowId = null): ?object
        {
            return null;
        }

        public function log(
            string $url,
            string $data,
            string $direction,
            bool $success
        ): void {
        }
    }
}

if (!class_exists('ModuleFields')) {
    class ModuleFields
    {
        public function fieldText(string $name, mixed $value = null, mixed $attributes = null): object
        {
            return new \stdClass();
        }

        public function fieldSelect(string $name, array $options = [], mixed $value = null, mixed $attributes = null): object
        {
            return new \stdClass();
        }

        public function fieldHidden(string $name, mixed $value = null): object
        {
            return new \stdClass();
        }

        public function label(string $label, string $for = null): object
        {
            return new \stdClass();
        }

        public function setField(object $field): void
        {
        }
    }
}

if (!class_exists('Language')) {
    class Language
    {
        public static function _(string $key, bool $return = false): string
        {
            return $key;
        }

        public static function loadLang(string $language, ?string $code, string $path): void
        {
        }
    }
}

if (!class_exists('Loader')) {
    class Loader
    {
        public static function loadHelpers(object $object, array $helpers): void
        {
        }

        public static function loadComponents(object $object, array $components): void
        {
        }
    }
}
