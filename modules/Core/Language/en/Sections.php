<?php

declare(strict_types=1);

/**
 * Sections of pages built from sections (Core registry, field types and forms; docs/sections.md).
 */
return [
    'items' => '{0} item(s)',
    'form'  => [
        'content'     => 'Content',
        'appearance'  => 'Appearance',
        'advanced'    => 'Advanced',
        'languages'   => 'Language of the texts:',
        'addItem'     => 'Add an item',
        'removeItem'  => 'Remove the item',
        'moveUp'      => 'Move up',
        'moveDown'    => 'Move down',
        'item'        => 'Item {0}',
        'itemActions' => 'Actions of the item',
        'itemsLimit'  => 'from {0} to {1}',
    ],
    'options' => [
        'style'               => 'Style',
        'background'          => 'Background',
        'background_image'    => 'Background image',
        'backgroundImageHelp' => 'A dark layer over the image keeps the text readable. The image is decorative.',
        'spacing'             => 'Spacing',
        'width'               => 'Width',
        'anchor'              => 'Anchor',
        'anchorHelp'          => 'Optional: lower-case letters, digits and hyphens. Links to "#name" go to this section.',
        'hidden'              => 'Hidden (not shown on the page)',
    ],
    'backgrounds' => [
        'none'    => 'None',
        'light'   => 'Light',
        'dark'    => 'Dark',
        'primary' => 'Main colour',
        'image'   => 'Image',
    ],
    'spacings' => [
        's' => 'Small',
        'm' => 'Medium',
        'l' => 'Large',
    ],
    'widths' => [
        'contained' => 'Contained',
        'full'      => 'Full width',
    ],
    'image' => [
        'alt'        => 'Alternative text',
        'altHelp'    => 'Describe what the image shows for people who cannot see it.',
        'decorative' => 'Decorative image (it adds no information: no alternative text)',
    ],
    'link' => [
        'type'  => 'Link to',
        'none'  => 'No link',
        'url'   => 'Address (https://… or /…)',
        'route' => 'Route of the site (name)',
        'value' => '{0}',
        'label' => 'Text of the link',
    ],
    'icon' => [
        'choose' => 'Choose',
        'search' => 'Search an icon',
        'help'   => 'The name of a Bootstrap icon (e.g. bi-star), or choose it in the grid.',
    ],
    'errors' => [
        'required'        => '"{0}" is required.',
        'requiredIn'      => '"{0}" is required in {1}.',
        'invalid'         => '"{0}" has a value that is not valid.',
        'tooLong'         => '"{0}" is too long (at most {1} characters).',
        'choice'          => '"{0}" has a choice that is not offered.',
        'number'          => '"{0}" must be a whole number.',
        'between'         => '"{0}" must be between {1} and {2}.',
        'icon'            => '"{0}" is not an icon of the list.',
        'image'           => '"{0}": this image cannot be used here.',
        'alt'             => '"{0}" needs an alternative text in {1}, or mark it as decorative.',
        'url'             => 'the address must start with https:// or be a path of this site starting with /.',
        'route'           => 'there is no route of the site with that name.',
        'linkType'        => 'that kind of link is not offered.',
        'linkChoice'      => 'choose one of the options.',
        'linkLabel'       => '"{0}" needs the text of the link in {1}.',
        'tooMany'         => '"{0}" has too many items (at most {1}).',
        'tooFew'          => '"{0}" needs at least {1} item(s).',
        'unknownField'    => 'The field "{0}" is not part of this section.',
        'options'         => 'The options of the section are not valid.',
        'anchor'          => 'The anchor may only have lower-case letters, digits and hyphens, and must start with a letter.',
        'backgroundImage' => 'Choose the background image, or another background.',
        'unknownType'     => 'This kind of section is not available.',
    ],
];
