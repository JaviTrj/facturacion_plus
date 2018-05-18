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

class valor_atributo_familia extends \fs_model
{

    public $idvaloratributo;
    public $idatributofamilia;
    public $nombre;
    public $orden;

    public function __construct($data = FALSE)
    {
        parent::__construct('valoresatributosfamilia');
        if ($data) {
            $this->idvaloratributo = array_key_exists('idvaloratributo', $data) ? $data['idvaloratributo'] : 0;
            $this->idatributofamilia = $data['idatributofamilia'];
            $this->nombre = $data['nombre'];
            $this->orden = isset($data['orden']) ? $data['orden'] : 0;
        } else {
            $this->idvaloratributo = NULL;
            $this->idatributofamilia = NULL;
            $this->nombre = '';
            $this->orden = 0;
        }
    }

    public function exists()
    {
        if (is_null($this->idvaloratributo)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idvaloratributo = " . $this->idvaloratributo . ";");
    }

    public function get($idvaloratributo)
    {
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idvaloratributo = " . $idvaloratributo . ";");
        if($data) {
            return new \valor_atributo_familia($data[0]);
        }

        return FALSE;
    }

    protected function install()
    {
        new \atributo_familia();

        return '';
    }

    public function test()
    {
        $status = FALSE;

        $this->nombre = $this->no_html($this->nombre);

        if (is_null($this->idatributofamilia)) {
            $this->new_error_msg("Un valor de atributo tiene que tener una referencia a un atributo.");
        } else if (_strlen($this->nombre) < 1 || _strlen($this->nombre) > 30) {
            $this->new_error_msg("Nombre de valor de atributo de familia no válido.");
        } else {
            $status = TRUE;
        }

        return $status;
    }

    public function save()
    {
        if ($this->test()) {
            $this->clean_cache();

            if ($this->exists()) {
                return $this->db->exec("UPDATE " . $this->table_name . " SET nombre = " . $this->var2str($this->nombre) .
                    ", idatributofamilia = " . $this->idatributofamilia .
                    ", orden = " . $this->orden .
                    " WHERE idvaloratributo = " . $this->idvaloratributo . ";");
            }

            $sql = "INSERT INTO " . $this->table_name . " (nombre,idatributofamilia,orden) VALUES " .
                "(" . $this->var2str($this->nombre) .
                "," . $this->idatributofamilia . 
                "," . $this->orden . ");";

            if($this->db->exec($sql)) {
                $this->idvaloratributo = $this->db->lastval();
                return TRUE;
            }
        }

        return FALSE;
    }

    public function delete()
    {
        $this->clean_cache();

        $sql = "DELETE FROM " . $this->table_name . " WHERE idvaloratributo = " . $this->idvaloratributo . ";";
        return $this->db->exec($sql);
    }

    // No se comprueba si el nuevo valor es nulo y el atributo no lo permite.
    public function cambiar_articulos_a($defecto) {
        $dato = new \atributo_articulo_familia();
        
        if(is_null($defecto)) {
            return $dato->delete_atributo_with_valor($this->idatributofamilia, $this->idvaloratributo);
        }
        
        return $dato->modify_atributo_with_valor($this->idatributofamilia, $this->idvaloratributo, $defecto);
    }

    private function clean_cache()
    {
        $this->cache->delete('m_valores_atributo_familia_'.$this->idatributofamilia);
    }

    public function get_valores_de_atributo($idatributofamilia)
    {
        $vallist = $this->cache->get_array('m_valores_atributo_familia_'.$idatributofamilia);
        if (!$vallist) {
            /// si la lista no está en caché, leemos de la base de datos
            $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idatributofamilia = " . $idatributofamilia . " ORDER BY orden ASC ;");
            if ($data) {
                foreach ($data as $d) {
                    $vallist[] = new \valor_atributo_familia($d);
                }
            }

            $this->cache->set('m_valores_atributo_familia_'.$idatributofamilia, $vallist);
        }

        return $vallist;
    }
}