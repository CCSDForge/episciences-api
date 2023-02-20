<?php

namespace App;

final class AppConstants
{
    public const APP_CONST = [
        'custom_operations' =>  [
            'get_stats_dashboard_collection'
        ],
        'normalizationContext' => ['groups' => [
            'review' => [
                'read' => [
                    'read:stats:Review',

                ]
            ]
        ]]

    ];

}