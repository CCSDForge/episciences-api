<?php

declare(strict_types=1);

namespace App\Service\Solr;

use App\AppConstants;

final class SolrConstants
{
    public const int SOLR_MAX_RETURNED_FACETS_RESULTS = AppConstants::MAXIMUM_ITEMS_PER_PAGE;
    public const string SOLR_FACET_SEPARATOR = '_FacetSep_';
    public const string SOLR_OTHERS_FACET_SEPARATOR = 'Others_FacetSep_';
    public const string SOLR_OTHERS_PREFIX = 'Others';
    public const string SOLR_ALL_PREFIX = 'All';
    public const string SOLR_INDEX = 'index';
    public const string SOLR_FACET_COUNT = 'count';
    public const string SOLR_FACET_NAME = 'name';
    public const string SOLR_LABEL = 'label';
}
