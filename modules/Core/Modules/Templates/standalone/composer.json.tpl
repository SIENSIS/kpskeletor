{
    "name": "{{vendor}}/{{name}}-module",
    "description": "{{Name}} module for the kpskeletor application skeleton.",
    "type": "kpskeletor-module",
    "license": "proprietary",
    "require": {
        "php": "^8.2"
    },
    "autoload": {
        "psr-4": {
            "Modules\\{{Name}}\\": ""
        }
    },
    "extra": {
        "kpskeletor": {
            "module": "{{Name}}",
            "core": "{{coreConstraint}}"
        }
    }
}
