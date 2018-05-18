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

class atributo_articulo_familia extends \fs_model
{

    public $referencia;
    public $idatributofamilia;
    public $valor;

    public function __construct($data = FALSE)
    {
        parent::__construct('atributosarticulosfamilia');
        if ($data) {
            $this->referencia = $data['referencia'];
            $this->idatributofamilia = $data['idatributofamilia'];
            $this->valor = $data['valor'];
        } else {
            $this->referencia = NULL;
            $this->idatributofamilia = NULL;
            $this->valor = '';
        }
    }

    public function exists()
    {
        if (is_null($this->idatributofamilia) || is_null($this->referencia)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idatributofamilia = " . $this->idatributofamilia . " AND referencia = " . $this->var2str($this->referencia) . ";");
    }

    protected function install()
    {
        new \atributo_familia();
        new \valor_atributo_familia();

        return '';
    }

    public function test()
    {
        $status = FALSE;

        $this->referencia = $this->no_html($this->referencia);

        if (is_null($this->idatributofamilia)) {
            $this->new_error_msg("Un valor de atributo en un articulo tiene que tener una referencia a un atributo.");
        } else if (strlen($this->referencia) < 1 || strlen($this->referencia) > 18) {
            $this->new_error_msg("Referencia de artículo en atributo de familia no válida, tiene que tener entre 1 y 18 caracteres.");
        } else {
            $status = TRUE;
        }

        return $status;
    }

    public function save()
    {
        if ($this->test()) {
            if ($this->exists()) {
                $sql = "UPDATE " . $this->table_name . " SET valor = " . $this->valor .
                    " WHERE idatributofamilia = " . $this->idatributofamilia . " AND referencia = " . $this->var2str($this->referencia) . ";";
            }
            else {
                $sql = "INSERT INTO " . $this->table_name . " (idatributofamilia,referencia,valor) VALUES " .
                    "(" . $this->idatributofamilia .
                    "," . $this->var2str($this->referencia) .
                    "," . $this->valor . ");";
            }

            return $this->db->exec($sql);
        }

        return FALSE;
    }

    public function delete()
    {
        $sql = "DELETE FROM " . $this->table_name . " WHERE idatributofamilia = " . $this->idatributofamilia . " AND referencia = " . $this->var2str($this->referencia) . ";";
        return $this->db->exec($sql);
    }

    public function delete_atributo_from_articulos($idatributofamilia, $lista_articulos) {
        $articulos = array_map(function($v) {
            return $this->var2str($v);
        }, $lista_articulos);
        $sql = "DELETE FROM " . $this->table_name . " WHERE idatributofamilia = " . $idatributofamilia . " AND referencia IN (" . implode(",", $articulos) . ");";
        return $this->db->exec($sql);
    }

    public function delete_atributo_with_valor($idatributo, $idvalor)
    {
        $sql = "DELETE FROM " . $this->table_name . " WHERE idatributofamilia = " . $idatributo . " AND valor = " . $idvalor . ";";
        return $this->db->exec($sql);
    }

    public function modify_atributo_with_valor($idatributo, $idvalor, $newOne)
    {
        $sql = "UPDATE " . $this->table_name . " SET valor = " . $newOne .
                    " WHERE idatributofamilia = " . $idatributo . " AND valor = " . $idvalor . ";";
        return $this->db->exec($sql);
    }

    public function get_atributos_de_articulo($referencia)
    {
        $vallist = array();
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE referencia = " . $this->var2str($referencia) . ";");
        if ($data) {
            foreach ($data as $d) {
                $vallist[] = new \atributo_articulo_familia($d);
            }
        }

        return $vallist;
    }

    public function get_valores_articulos($articulos) {
        $referencias = implode(',',array_map(function($v) {
            return $this->var2str($v->referencia);
        }, $articulos));

        return $this->db->select("SELECT atributosfamilia.nombre, (CASE atributosfamilia.tipo WHEN 0 THEN atributosarticulosfamilia.valor WHEN 1 THEN IF(atributosarticulosfamilia.valor,'Verdadero','Falso') WHEN 2 THEN valoresatributosfamilia.nombre END) AS dato, referencia FROM " . $this->table_name . " LEFT JOIN atributosfamilia ON atributosarticulosfamilia.idatributofamilia = atributosfamilia.idatributofamilia LEFT JOIN valoresatributosfamilia ON atributosarticulosfamilia.valor = valoresatributosfamilia.idvaloratributo WHERE referencia IN (". $referencias.");"); 
    }
}