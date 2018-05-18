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

require_once 'plugins/facturacion_base/model/core/stock.php';

/**
 * La cantidad en inventario de un artículo en un almacén concreto.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class stock extends FacturaScripts\model\stock
{

    // Devuelve la cantidad que se ha podido reservar
    public function sum_reservada($c = 0)
    {   
        if ($this->reservada+$c < 0) {
            $this->new_error_msg("La cantidad del artículo reservada no puede ser negativa");
            return 0;
        }

        if ($c > $this->disponible) {
            $this->new_error_msg("No hay suficiente disponible de ".$this->referencia." para reservar ".$c.". ".($this->disponible > 0 ? "Se reservaron solamente ".$this->disponible : "No se pudo reservar nada").".");
            $reservados = $this->disponible;
            $this->reservada += $reservados;
            $this->disponible = 0;
            return $reservados;
        }

        $this->reservada += $c;
        $this->disponible -= $c;
        return $c;
    }

    public function set_cantidad($c = 0)
    {
        parent::set_cantidad($c);
        if($this->disponible < 0 && $this->reservada > 0) {
            $this->new_error_msg("La cantidad reservada del artículo con referencia ".$this->referencia." es menor que lo almacenado en ".$this->codalmacen.".");
        }
    }

    public function sum_cantidad($c = 0)
    {
        parent::sum_cantidad($c);
        if($this->disponible < 0 && $this->reservada > 0) {
            $this->new_error_msg("La cantidad reservada del artículo con referencia ".$this->referencia." es menor que lo almacenado en ".$this->codalmacen.".");
        }
    }

}
