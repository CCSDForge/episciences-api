<?php

declare(strict_types=1);

namespace App;

use App\Entity\Review;

final class AppConstants
{
    public const int MAXIMUM_ITEMS_PER_PAGE = 1000;
    public const int DEFAULT_ITEM_PER_PAGE = 30;
    public const string BASE_64 = 'base64';
    public const int DEFAULT_PRECISION = 0;
    public const int RATE_DEFAULT_PRECISION = 2;
    public const string IS_APP_ITEM = 'isAppItem';
    public const string IS_APP_COLLECTION = 'isAppCollection';

    public const string ORDER_ASC = 'ASC';
    public const string ORDER_DESC = 'DESC';
    public const array AVAILABLE_FILTERS = ['rvid', 'repoid', 'status', 'submissionDate', 'withDetails', 'startAfterDate', 'flag', AppConstants::YEAR_PARAM];
    public const string WITH_DETAILS = 'withDetails';
    public const string PAPER_STATUS = 'status';
    public const string PAPER_FLAG = 'flag';
    public const string START_AFTER_DATE = 'startAfterDate';
    public const string SUBMISSION_DATE = 'submissionDate';
    public const string STATS_DASHBOARD_ITEM = 'get_stats_dashboard_item';
    public const string STATS_NB_SUBMISSIONS_ITEM = 'get_stats_nb_submissions_item';
    public const string STATS_DELAY_SUBMISSION_ACCEPTANCE = 'get_delay_between_submit_and_acceptance_item';
    public const string STATS_DELAY_SUBMISSION_PUBLICATION = 'get_delay_between_submit_and_publication_item';
    public const string STATS_NB_USERS = 'get_stats_nb_users_item';
    public const string YEAR_PARAM = 'year';
    public const string FILTER_TYPE_EXACT = 'exact';
    public const array APP_CONST = [
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
