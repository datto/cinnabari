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

namespace Datto\Cinnabari\Fluff;

use Datto\Cinnabari\Fluff\Optimizer\RemoveUnusedSorts;
use Datto\Cinnabari\Fluff\Optimizer\InsertDirectly;
use Datto\Cinnabari\Fluff\Optimizer\FilterBeforeSort;

class Optimizer
{
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
        // Placeholder (for when this code is ready to release)
        return $request;
    }
}
