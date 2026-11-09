<?php

declare(strict_types=1);

/*
 * Dashboard module (en).
 */
return [
    'res'  => ['dashboard' => 'Dashboard'],
    'perm' => ['configure' => 'Configure the group layouts'],
    'menu' => [
        'home'   => 'Dashboard',
        'groups' => 'Dashboard widgets',
    ],
    'title'          => 'Dashboard',
    'customize'      => 'Customise',
    'customizeHelp'  => 'Choose the order, size and visibility of your widgets.',
    'done'           => 'Done',
    'editHelp'       => 'Drag the widgets by their handle (or use the arrow keys on it) to reorder them, change their size or hide them. Changes are saved at once.',
    'restore'        => 'Restore the group layout',
    'restoreConfirm' => 'Your own order, sizes and hidden widgets will be forgotten. Continue?',
    'empty'          => 'No widgets to show',
    'emptyHelp'      => 'Your groups have no widgets enabled, or you lack the permissions they need.',
    'emptyHidden'    => 'You have hidden every widget. Use “Customise” to show them again.',
    'hiddenTitle'    => 'Hidden widgets',
    'hiddenHelp'     => 'Use “Customise” to show them again.',
    'widget'         => 'Widget',
    'position'       => 'Position',
    'size'           => 'Size',
    'visible'        => 'Visible',
    'cancel'         => 'Cancel',
    'save'           => 'Save',
    'saved'          => 'Your dashboard layout has been saved.',
    'restored'       => 'The group layout has been restored.',
    'invalid'        => 'The layout could not be read.',
    'forbidden'      => 'You cannot see this widget.',
    'groups'         => [
        'title'           => 'Group layouts',
        'subtitle'        => 'Which widgets each group has, in which order, size and options.',
        'groups'          => 'Groups',
        'help'            => 'Widgets enabled for “{0}” and their defaults. Users only see a widget when they also hold its permission, and they can reorder, resize and hide their own widgets.',
        'none'            => 'No module offers widgets.',
        'noPermission'    => 'This group does not hold {0}: its users will not see the widget unless they get the permission elsewhere.',
        'permission'      => 'Permission:',
        'saved'           => 'The group layout has been saved.',
        'invalidPosition' => '“{0}”: the position must be a whole number between 0 and 9999.',
        'invalidSize'     => '“{0}”: choose one of the sizes offered.',
    ],
    'js' => [
        'move'  => 'Move (arrow keys)',
        'size'  => 'Size',
        'hide'  => 'Hide',
        'show'  => 'Show',
        'saved' => 'Saved',
        'moved' => 'Moved to position {0}',
    ],
    'activity' => [
        'group_layout_saved' => 'Dashboard group layout saved',
        'widget_forbidden'   => 'Widget data refused',
    ],
];
