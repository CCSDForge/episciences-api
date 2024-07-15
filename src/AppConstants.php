<?php

namespace App;

use App\Entity\Review;

final class AppConstants
{
    public const IS_APP_ITEM = 'isAppItem';
    public const IS_APP_COLLECTION = 'isAppCollection';
    public const ORDER_DESC = 'DESC';
    public const AVAILABLE_FILTERS = ['rvid', 'repoid', 'status', 'submissionDate', 'withDetails', 'startAfterDate', 'flag', AppConstants::YEAR_PARAM];
    public const WITH_DETAILS = 'withDetails';
    public const PAPER_STATUS = 'status';
    public const PAPER_FLAG = 'flag';
    public const START_AFTER_DATE = 'startAfterDate';
    public const SUBMISSION_DATE = 'submissionDate';
    public const STATS_DASHBOARD_ITEM = 'get_stats_dashboard_item';
    public const STATS_NB_SUBMISSIONS_ITEM = 'get_stats_nb_submissions_item';
    public const STATS_DELAY_SUBMISSION_ACCEPTANCE = 'get_delay_between_submit_and_acceptance_item';
    public const STATS_DELAY_SUBMISSION_PUBLICATION = 'get_delay_between_submit_and_publication_item';
    public const STATS_NB_USERS = 'get_stats_nb_users_item';
    public const YEAR_PARAM = 'year';
    public const FILTER_TYPE_EXACT = 'exact';
    public const APP_CONST = [
        'custom_operations' => [
            'items' => [
                'review' => [ // the order of the elements is important
                    self::STATS_DASHBOARD_ITEM,
                    self::STATS_NB_SUBMISSIONS_ITEM,
                    self::STATS_DELAY_SUBMISSION_ACCEPTANCE,
                    self::STATS_DELAY_SUBMISSION_PUBLICATION,
                    self::STATS_NB_USERS

                ],
                'papers' => [ // the order of the elements is important

                ]
            ],
            'uri_template' => [
                self::STATS_DASHBOARD_ITEM => Review::URI_TEMPLATE . 'stats/dashboard/{code}',
                self::STATS_NB_SUBMISSIONS_ITEM => Review::URI_TEMPLATE . 'stats/nb-submissions/{code}',
                self::STATS_DELAY_SUBMISSION_ACCEPTANCE => Review::URI_TEMPLATE . 'stats/delay-submission-acceptance/{code}',
                self::STATS_DELAY_SUBMISSION_PUBLICATION => Review::URI_TEMPLATE . 'stats/delay-submission-publication/{code}',
                self::STATS_NB_USERS => Review::URI_TEMPLATE . 'stats/nb-users/{code}',
            ]

        ],
        'normalizationContext' => [
            'groups' => [
                'review' => [
                    'item' => [
                        'read' => [
                            'read:stats:Review',
                        ],
                    ]

                ],
                'papers' => [
                    'item' => [
                        'read' => [
                            'read:Paper'
                        ]
                    ],
                    'collection' => [
                        'read' => [
                            'read:Papers'
                        ]
                    ]
                ],
                'user' => [
                    'item' => [
                        'read' => [
                            'read:User'
                        ]
                    ],
                    'collection' => [
                        'read' => [
                            'read:Users'
                        ]
                    ]

                ],
                'userRoles' => [
                    'item' => [
                        'read' => [
                            'read:UserRoles'
                        ]
                    ],
                    'collection' => [
                        'read' => [
                            'read:UsersRoles'
                        ]
                    ]

                ],
                'volume' => [
                    'item' => [
                        'read' => [
                            'read:Volume'
                        ]
                    ],
                    'collection' => [
                        'read' => [
                            'read:Volumes'
                        ]
                    ]

                ],
                'section' => [
                    'item' => [
                        'read' => [
                            'read:Section'
                        ]
                    ],
                    'collection' => [
                        'read' => [
                            'read:Sections'
                        ]
                    ]

                ]
            ]]

    ];

}