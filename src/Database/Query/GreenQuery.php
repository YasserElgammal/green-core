<?php

namespace YasserElgammal\Green\Database\Query;

use YasserElgammal\Green\Database\Model;
use YasserElgammal\Green\Database\Query\Traits\BuildsConditions;
use YasserElgammal\Green\Database\Query\Traits\FetchesResults;
use YasserElgammal\Green\Database\Query\Traits\OrdersQuery;
use YasserElgammal\Green\Database\Query\Traits\RunsAggregates;
use YasserElgammal\Green\Database\Query\Traits\SelectsColumns;

/**
 * Fluent query wrapper for Green tables.
 *
 * @template TModel of Model
 * @extends GreenQueryState<TModel>
 */
class GreenQuery extends GreenQueryState
{
    use BuildsConditions;
    use OrdersQuery;
    use FetchesResults;
    use RunsAggregates;
    use SelectsColumns;
}