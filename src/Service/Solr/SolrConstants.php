<?php

namespace App\Service\Solr;

use App\AppConstants;

final class SolrConstants
{
    public const SOLR_MAX_RETURNED_FACETS_RESULTS = AppConstants::MAXIMUM_ITEMS_PER_PAGE;
    public const SOLR_FACET_SEPARATOR = '_FacetSep_';
    public const SOLR_OTHERS_FACET_SEPARATOR = 'Others_FacetSep_';
    public const SOLR_OTHERS_PREFIX = 'Others';
    public const SOLR_ALL_PREFIX = 'All';
    public const SOLR_INDEX = 'index';
    public const SOLR_FACET_COUNT = 'count';
    public const SOLR_FACET_NAME = 'name';
    public const SOLR_LABEL = 'label';
}
