<?php
namespace JEALER\G3\Services;
use JEALER\G3\Core\Service\Service;
use WP_Taxonomy;

class TaxonomyService extends Service {
    private ?WP_Taxonomy $taxonomy = null;
}
