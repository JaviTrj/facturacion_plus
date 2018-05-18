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

require_once 'plugins/facturacion_base/model/core/familia.php';

/**
 * Una familia o categoría de artículos.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class familia extends FacturaScripts\model\familia
{
 
    
    public function get_lista_madres($codfamilia = NULL)
    {
        $madre = $this->madre;
        if(!is_null($codfamilia)) {
            $madre = $this->get($codfamilia)->madre;
        }

        if(isset($madre)) {
            $madres_de_madre = $this->get($madre)->get_lista_madres();
            $madres_de_madre[] = $madre;
            return $madres_de_madre;
        }
        
        return array();
    }

    public function get_lista_hijas($codfamilia, $familias = NULL)
    {
        if(is_null($familias)) {
            $familias = $this->all();
        }

        return array_reduce($familias, function($acc,$it) use(&$codfamilia,&$familias) {
            return $it->madre == $codfamilia
                ? array_merge($acc, array($it->codfamilia), $this->get_lista_hijas($it->codfamilia, $familias))
                : $acc;
        }, array());
    }

    public function get_articulos($offset = 0, $limit = FS_ITEM_LIMIT)
    {
        $articulos = parent::get_articulos($offset,$limit);
        if($articulos) {
            $articulo = new articulo();
            $articulo->agnadir_atributos($articulos);
        }
        return $articulos;
    }

    public function numero_atributos()
    {
        if(is_null($this->codfamilia)) {
            return 0;
        }
        $atributo = new atributo_familia();
        return $atributo->count_from_familia($this->codfamilia);
    }
}
