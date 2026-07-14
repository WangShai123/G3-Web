<?php
namespace JEALER\G3\Services;
use JEALER\G3\Core\Service\Service;
use WP_Term;

class TermService extends Service {
    private ?WP_Term $term = null;
    public function __construct()
    {
        parent::__construct();
    }
    public function init(): ?WP_Term
    {
        $term = get_queried_object();
        if ($term instanceof WP_Term) {
            $this->term = $term;
            return $this->term;
        }
        return null;
    }
}
