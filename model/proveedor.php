<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2013-2017  Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once 'plugins/facturacion_base/model/core/proveedor.php';

/**
 * Un proveedor. Puede estar relacionado con varias direcciones o subcuentas.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class proveedor extends FacturaScripts\model\proveedor
{
    public function get_metodo_pendientes() {
        $metodo = new proveedor_opcion_pendientes();
        return $metodo->get($this->codproveedor);
    }
}