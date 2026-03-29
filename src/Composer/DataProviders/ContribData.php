<?php

namespace Moodle\Composer\DataProviders;

class ContribData extends \Moodle\Composer\DataProvider
{
    /**
     * {@inheritDoc}
     */
    function getData(): array
    {
        return [
            // tool_certificate subplugin types.
            'certificateelement'  => '{$prefix}{$public}admin/tool/certificate/element/{$name}/',

            // mod_customcert subplugin types.
            'customcertelement'  => '{$prefix}{$public}mod/customcert/element/{$name}/',

            // tool_lifecycle subplugin types.
            'lifecycletrigger'   => '{$prefix}{$public}admin/tool/lifecycle/trigger/',
            'lifecyclestep'      => '{$prefix}{$public}admin/tool/lifecycle/step/',
        ];
    }
}
