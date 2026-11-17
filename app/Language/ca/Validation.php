<?php

declare(strict_types=1);

/*
 * Catalan texts of CodeIgniter's validation (`codeigniter4/translations` has no Catalan).
 * Same keys as system/Language/en/Validation.php (checked by I18nKeysTest).
 */
return [
    // Core Messages
    'noRuleSets'      => 'No s’ha indicat cap conjunt de regles a la configuració de la validació.',
    'ruleNotFound'    => '«{0}» no és una regla vàlida.',
    'groupNotFound'   => '«{0}» no és un grup de regles de validació.',
    'groupNotArray'   => 'El grup de regles «{0}» ha de ser una llista.',
    'invalidTemplate' => '«{0}» no és una plantilla de validació vàlida.',

    // Rule Messages
    'alpha'                 => 'El camp {field} només pot contenir lletres.',
    'alpha_dash'            => 'El camp {field} només pot contenir caràcters alfanumèrics, guions baixos i guions.',
    'alpha_numeric'         => 'El camp {field} només pot contenir caràcters alfanumèrics.',
    'alpha_numeric_punct'   => 'El camp {field} només pot contenir caràcters alfanumèrics, espais i els caràcters ~ ! # $ % & * - _ + = | : .',
    'alpha_numeric_space'   => 'El camp {field} només pot contenir caràcters alfanumèrics i espais.',
    'alpha_space'           => 'El camp {field} només pot contenir lletres i espais.',
    'decimal'               => 'El camp {field} ha de contenir un nombre decimal.',
    'differs'               => 'El camp {field} ha de ser diferent del camp {param}.',
    'equals'                => 'El camp {field} ha de ser exactament: {param}.',
    'exact_length'          => 'El camp {field} ha de tenir exactament {param} caràcters.',
    'field_exists'          => 'El camp {field} ha d’existir.',
    'greater_than'          => 'El camp {field} ha de contenir un nombre més gran que {param}.',
    'greater_than_equal_to' => 'El camp {field} ha de contenir un nombre més gran o igual que {param}.',
    'hex'                   => 'El camp {field} només pot contenir caràcters hexadecimals.',
    'in_list'               => 'El camp {field} ha de ser un d’aquests valors: {param}.',
    'integer'               => 'El camp {field} ha de contenir un nombre enter.',
    'is_natural'            => 'El camp {field} només pot contenir dígits.',
    'is_natural_no_zero'    => 'El camp {field} només pot contenir dígits i ha de ser més gran que zero.',
    'is_not_unique'         => 'El camp {field} ha de contenir un valor que ja existeixi a la base de dades.',
    'is_unique'             => 'El camp {field} ha de contenir un valor únic.',
    'less_than'             => 'El camp {field} ha de contenir un nombre més petit que {param}.',
    'less_than_equal_to'    => 'El camp {field} ha de contenir un nombre més petit o igual que {param}.',
    'matches'               => 'El camp {field} no coincideix amb el camp {param}.',
    'max_length'            => 'El camp {field} no pot tenir més de {param} caràcters.',
    'min_length'            => 'El camp {field} ha de tenir com a mínim {param} caràcters.',
    'not_equals'            => 'El camp {field} no pot ser: {param}.',
    'not_in_list'           => 'El camp {field} no pot ser cap d’aquests valors: {param}.',
    'numeric'               => 'El camp {field} només pot contenir nombres.',
    'regex_match'           => 'El camp {field} no té el format correcte.',
    'required'              => 'El camp {field} és obligatori.',
    'required_with'         => 'El camp {field} és obligatori quan hi ha {param}.',
    'required_without'      => 'El camp {field} és obligatori quan no hi ha {param}.',
    'string'                => 'El camp {field} ha de ser un text vàlid.',
    'timezone'              => 'El camp {field} ha de ser una zona horària vàlida.',
    'valid_base64'          => 'El camp {field} ha de ser un text base64 vàlid.',
    'valid_email'           => 'El camp {field} ha de contenir una adreça electrònica vàlida.',
    'valid_emails'          => 'El camp {field} ha de contenir només adreces electròniques vàlides.',
    'valid_ip'              => 'El camp {field} ha de contenir una adreça IP vàlida.',
    'valid_url'             => 'El camp {field} ha de contenir una URL vàlida.',
    'valid_url_strict'      => 'El camp {field} ha de contenir una URL vàlida.',
    'valid_date'            => 'El camp {field} ha de contenir una data vàlida.',
    'valid_json'            => 'El camp {field} ha de contenir un JSON vàlid.',

    // Credit Cards
    'valid_cc_number' => 'El camp {field} no sembla un número de targeta de crèdit vàlid.',

    // Files
    'uploaded' => 'El camp {field} no és un fitxer pujat vàlid.',
    'max_size' => 'El fitxer del camp {field} és massa gran.',
    'is_image' => 'El camp {field} no és una imatge pujada vàlida.',
    'mime_in'  => 'El camp {field} no té un tipus MIME vàlid.',
    'ext_in'   => 'El camp {field} no té una extensió de fitxer vàlida.',
    'max_dims' => 'El camp {field} no és una imatge o és massa ample o massa alt.',
    'min_dims' => 'El camp {field} no és una imatge o no és prou ample o prou alt.',
];
