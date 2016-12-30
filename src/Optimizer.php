<?php

/**
 * Copyright (C) 2016 Datto, Inc.
 *
 * This file is part of Cinnabari.
 *
 * Cinnabari is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * Cinnabari is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with Cinnabari. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Spencer Mortensen <smortensen@datto.com>
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL-3.0
 * @copyright 2016 Datto, Inc.
 */

namespace Datto\Cinnabari;

use Datto\Cinnabari\Optimizer\AlwaysSortGets;
use Datto\Cinnabari\Optimizer\RemoveUnusedSorts;
use Datto\Cinnabari\Optimizer\InsertDirectly;
use Datto\Cinnabari\Optimizer\FilterBeforeSort;
use Datto\Cinnabari\Schema;

class Optimizer
{
    private $schema;

    public function __construct(Schema $schema)
    {
        $this->schema = $schema;
    }

    public function optimize($request)
    {
        $request = $this->removeUnusedSorts($request);
        $request = $this->insertDirectly($request);
        $request = $this->filterBeforeSort($request);
        $request = $this->alwaysSortGets($request);

        return $request;
    }

    private function removeUnusedSorts($request)
    {
        $optimizer = new RemoveUnusedSorts();
        return $optimizer->optimize($request);
    }

    private function insertDirectly($request)
    {
        $optimizer = new InsertDirectly();
        return $optimizer->optimize($request);
    }

    private function filterBeforeSort($request)
    {
        $optimizer = new FilterBeforeSort();
        return $optimizer->optimize($request);
    }

    private function alwaysSortGets($request)
    {
        $optimizer = new AlwaysSortGets($this->schema);
        return $optimizer->optimize($request);
    }
}
