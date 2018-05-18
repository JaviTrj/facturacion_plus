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
require_once 'plugins/facturacion_base/model/core/fabricante.php';

/**
 * Un fabricante de artículos.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class fabricante extends FacturaScripts\model\fabricante
{

    public $asgproveedor;

    public function __construct($data = FALSE)
    {
        parent::__construct($data);
        if ($data) {
            $this->asgproveedor = $data['asgproveedor'];
        } else {
            $this->asgproveedor = NULL;
        }
    }

    protected function install()
    {
        $this->clean_cache();
        return "INSERT INTO " . $this->table_name . " (codfabricante,nombre,asgproveedor) VALUES ('OEM','OEM',NULL);";
    }

    public function get_proveedores()
    {
        $proveedor = new \proveedor();
        return $proveedor->all();
    }

    protected function clean_cache()
    {
        $this->cache->delete('m_fabricante_all');
    }

    public function test()
    {
        if(!parent::test()) {
            return FALSE;
        }

        $this->asgproveedor = $this->no_html($this->asgproveedor);

        if (strlen($this->asgproveedor) < 1 || strlen($this->asgproveedor) > 6) {
            $this->new_error_msg("Código de proveedor asignado no válido. Deben ser entre 1 y 6 caracteres.");
            return FALSE;
        }

        return TRUE;
    }

    public function save()
    {
        if ($this->test()) {
            $this->clean_cache();

            if ($this->exists()) {
                $sql = "UPDATE " . $this->table_name . " SET nombre = " . $this->var2str($this->nombre) .
                    ", asgproveedor = " . $this->var2str($this->asgproveedor) .
                    " WHERE codfabricante = " . $this->var2str($this->codfabricante) . ";";
            } else {
                $sql = "INSERT INTO " . $this->table_name . " (codfabricante,nombre,asgproveedor) VALUES " .
                    "(" . $this->var2str($this->codfabricante) .
                    "," . $this->var2str($this->nombre) .
                    "," . $this->var2str($this->asgproveedor) . ");";
            }

            return $this->db->exec($sql);
        }

        return FALSE;
    }

    public function all_from_proveedor($codproveedor)
    {
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE asgproveedor = ". $this->var2str($codproveedor) .";");
        if ($data) {
            $fablist = array();
            foreach ($data as $d) {
                $fablist[] = new \fabricante($d);
            }
            return $fablist;
        }

        return FALSE;
    }
}
