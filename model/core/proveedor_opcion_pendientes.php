<?php
/*
 * Copyright (C) 2018 Javier Trujillo González <javimeteo@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See th * e
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace FacturaScripts\model;

class proveedor_opcion_pendientes extends \fs_model
{
    public $codproveedor;
    public $codopcion;

    public function __construct($data = FALSE)
    {
        parent::__construct('proveedoropcionpendientes');
        if ($data) {
            $this->codproveedor = $data["codproveedor"];
            $this->codopcion = $data["codopcion"];
        }
        else {
            $this->codproveedor = NULL;
            $this->codopcion = NULL;
        }
    }

    public function exists()
    {
        if (is_null($this->codproveedor)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE codproveedor = " . $this->var2str($this->codproveedor) . ";");
    }

    public function get($codproveedor)
    {
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE codproveedor = " . $this->var2str($codproveedor) . ";");
        if($data) {
            return new \proveedor_opcion_pendientes($data[0]);
        }
        $op = new \opcion_pendientes_proveedor();
        $default = $op->get_por_defecto();
        if($default) {
            return $default;
        }
        
        return FALSE;
    }

    public function install() {
        new \opcion_pendientes_proveedor();

        return '';
    }

    public function test()
    {
        $status = FALSE;

        $this->codproveedor = $this->no_html($this->codproveedor);
        $this->codopcion = $this->no_html($this->codopcion);

        if (is_null($this->codproveedor) || strlen($this->codproveedor) < 1 || strlen($this->codproveedor) > 6) {
            $this->new_error_msg("Código de proveedor invalido, debe tener entre 1 y 6 caracteres.");
        } elseif (is_null($this->codopcion) || strlen($this->codopcion) < 1 || strlen($this->codopcion) > 10) {
            $this->new_error_msg("Código de opción invalido, debe tener entre 1 y 10 caracteres.");
        } else {
            $status = TRUE;
        }

        return $status;
    }

    public function save()
    {
        if ($this->test()) {

            if ($this->exists()) {
                return $this->db->exec("UPDATE " . $this->table_name . " SET codopcion = " . $this->var2str($this->codopcion) .
                    " WHERE codproveedor = " . $this->var2str($this->codproveedor) . ";");
            }
            
            $sql = "INSERT INTO " . $this->table_name . " (codproveedor,codopcion) VALUES " .
                    "(" . $this->var2str($this->codproveedor) .
                    "," . $this->var2str($this->codopcion) . ");";
            
            return $this->db->exec($sql);
        }

        return FALSE;
    }

    public function delete()
    {
        $sql = "DELETE FROM " . $this->table_name . " WHERE codproveedor = " . $this->var2str($this->codproveedor) . ";";
        return $this->db->exec($sql);
    }
}