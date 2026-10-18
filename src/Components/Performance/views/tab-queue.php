<?php
use JEALER\G3\Utilities\Element;

echo Element::tip(
    __('If you want to use scheduled tasks, please use the Queue and Job services provided by G3-Web, which combine systemd or supervisor for high-performance and highly maintainable system-level features.', 'G3'),
    '',
    'default',
    'mt-4'
);
