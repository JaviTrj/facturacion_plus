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

class opcion_pendientes_proveedor extends \fs_model
{
    public $codopcion;
    public $descripcion;

    public function __construct($data = FALSE)
    {
        parent::__construct('opcionesproveedorespendientes');
        if ($data) {
            $this->codopcion = $data["codopcion"];
            $this->descripcion = $data["descripcion"];
        }
        else {
            $this->codopcion = NULL;
            $this->descripcion = NULL;
        }
    }

    public function exists()
    {
        if (is_null($this->codopcion)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE codopcion = " . $this->var2str($this->codopcion) . ";");
    }

    public function install() {
        return "INSERT INTO " . $this->table_name . ' (codopcion,descripcion) VALUES ' .
            '("nopermitir","No permite pendientes"),'.
            '("si_acumular","Un pendiente sigue esperandose si se pide más posteriormente"),'.
            '("si_pisar","Un pendiente desaparece si se pide de nuevo posteriormente");';
    }

    public function get($codopcion)
    {
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE codopcion = " . $this->var2str($codopcion) . ";");
        if($data) {
            return new \opcion_pendientes_proveedor($data[0]);
        }
        
        return FALSE;
    }

    public function get_por_defecto() {
        return $this->get("nopermitir");
    }

    public function test()
    {
        $status = FALSE;

        $this->codopcion = $this->no_html($this->codopcion);
        $this->opcion_pendientes = $this->no_html($this->opcion_pendientes);

        if (is_null($this->codopcion) || strlen($this->codopcion) < 1 || strlen($this->codopcion) > 10) {
            $this->new_error_msg("Código de opcion invalido, debe tener entre 1 y 10 caracteres.");
        } elseif (is_null($this->descripcion) || strlen($this->descripcion) < 1 || strlen($this->descripcion) > 60) {
                $this->new_error_msg("Descripción de opcion invalida, debe tener entre 1 y 60 caracteres.");
        } else {
            $status = TRUE;
        }

        return $status;
    }

    public function save()
    {
        if ($this->test()) {

            if ($this->exists()) {
                return $this->db->exec("UPDATE " . $this->table_name . " SET descripcion = " . $this->var2str($this->descripcion) .
                    " WHERE codopcion = " . $this->var2str($this->codopcion) . ";");
            }
            
            $sql = "INSERT INTO " . $this->table_name . " (codopcion,descripcion) VALUES " .
                    "(" . $this->var2str($this->codopcion) .
                    "," . $this->var2str($this->descripcion) . ");";
            
            return $this->db->exec($sql);
        }

        return FALSE;
    }

    public function delete()
    {
        $sql = "DELETE FROM " . $this->table_name . " WHERE codopcion = " . $this->var2str($this->codopcion) . ";";
        return $this->db->exec($sql);
    }

    public function all() {
        $data = $this->db->select("SELECT * FROM " . $this->table_name);
        if($data) {
            $oplist = array();
            foreach($data as $d) {
                $oplist[] = new \opcion_pendientes_proveedor($d);
            }

            return $oplist;
        }

        return FALSE;
    }
}