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

class pendiente_proveedor extends \fs_model
{
    public $idpendiente;
    public $referencia;
    public $cantidad;
    public $idalbaran;

    public function __construct($data = FALSE)
    {
        parent::__construct('pendientesproveedores');
        if ($data) {
            $this->idpendiente = $data["idpendiente"];
            $this->referencia = $data["referencia"];
            $this->cantidad = $data["cantidad"];
            $this->idalbaran = $data["idalbaran"];
        }
        else {
            $this->idpendiente = NULL;
            $this->referencia = NULL;
            $this->cantidad = 0;
            $this->idalbaran = NULL;  
        }
    }

    public function exists()
    {
        if (is_null($this->idpendiente)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idpendiente = " . $this->idpendiente . ";");
    }

    public function test()
    {
        $status = FALSE;

        $this->referencia = $this->no_html($this->referencia);

        if (is_null($this->referencia) || strlen($this->referencia) < 1 || strlen($this->referencia) > 18) {
            $this->new_error_msg("Referencia de artículo del pendiente de proveedor no válida. Debe tener entre 1 y 18 caracteres.");
        } else if (is_null($this->idalbaran)) {
            $this->new_error_msg("Id del albarán no definido.");
        } else if ($this->cantidad <= 0) {
            $this->new_error_msg("La cantidad de pendientes tiene que ser mayor que 0.");
        } else {
            $status = TRUE;
        }

        return $status;
    }

    public function save()
    {
        if ($this->test()) {

            if ($this->exists()) {
                return $this->db->exec("UPDATE " . $this->table_name . " SET referencia = " . $this->var2str($this->referencia) .
                    ", idalbaran = " . $this->var2str($this->idalbaran) .
                    ", cantidad = " . $this->cantidad .
                    " WHERE idpendiente = " . $this->idpendiente . ";");
            }
            
            $sql = "INSERT INTO " . $this->table_name . " (referencia,cantidad,idalbaran) VALUES " .
                    "(" . $this->var2str($this->referencia) .
                    "," . $this->cantidad .
                    "," . $this->var2str($this->idalbaran) . ");";
            
            if($this->db->exec($sql)) {
                $this->idpendiente = $this->db->lastval();
                return TRUE;
            }
        }

        return FALSE;
    }

    public function delete()
    {
        $sql = "DELETE FROM " . $this->table_name . " WHERE idpendiente = " . $this->idpendiente . ";";
        return $this->db->exec($sql);
    }

    public function get_all_from_proveedor($codproveedor)
    {
        $data = $this->db->select("SELECT pd.* FROM " . $this->table_name . " pd LEFT JOIN albaranesprov alb ON pd.idalbaran = alb.idalbaran WHERE alb.codproveedor = " . $this->var2str($codproveedor) . ";");
        if($data) {
            $pendientes = array();
            foreach($data as $d) {
                $pendientes[] = new \pendiente_proveedor($d);
            }

            return $pendientes;
        }

        return FALSE;
    }

    public function count_articulos_de_proveedor($codproveedor,$codalmacen,$referencia)
    {
        $data = $this->db->select("SELECT SUM(cantidad) as cuantos FROM " . $this->table_name . " p LEFT JOIN albaranesprov a ON p.idalbaran = a.idalbaran WHERE a.codproveedor = " . $this->var2str($codproveedor) . ($codalmacen ? " AND codalmacen = " . $this->var2str($codalmacen) : "") . " AND p.referencia = " . $this->var2str($referencia) . " GROUP BY p.referencia;");
        if($data) {
            return $data[0]['cuantos'];
        }

        return FALSE;
    }

    public function delete_articulos_de_proveedor($codproveedor,$codalmacen,$cuales,$tienen_que_eliminarse_todos)
    {
        // Comprobarlos
        $eliminar = array();
        $modificar = array();
        foreach($cuales as $cual) {
            $encontrados = $this->count_articulos_de_proveedor($codproveedor,$codalmacen,$cual['referencia']);
            if($tienen_que_eliminarse_todos) {
                if($encontrados != $cual['cuantos']) { 
                    $this->new_error_msg("El número de pendientes del artículo ".$cual['referencia']." que se iban a eliminar(".$encontrados.") no concuerda con el número que se tuvieron en cuenta al aumentar el pedido(".$cual['cuantos'].")");    
                    return FALSE;
                }
                $eliminar[] = $cual['referencia'];
            }
            else {
                if($cual['cuantos'] > $encontrados) {
                    $this->new_error_msg("El número de pendientes del artículo ".$cual['referencia']." que se iban a eliminar(".$cual['cuantos'].") es mayor que los que hay pendientes (".$encontrados.")");    
                    return FALSE;
                }
                else if($cual['cuantos'] == $encontrados) {
                    $eliminar[] = $cual['referencia'];
                }
                else {
                    $modificar[] = $cual;
                }
            }
        }

        // Eliminarlos
        $sql = "";
        if(count($eliminar)) {
            $referencias = implode(",",array_map(function($v) {
                return $this->var2str($v);
            },$eliminar));
            $sql .= "DELETE FROM " . $this->table_name . " WHERE idalbaran IN (SELECT idalbaran FROM albaranesprov WHERE codproveedor = " . $this->var2str($codproveedor) . ($codalmacen ? " AND codalmacen = " . $this->var2str($codalmacen) : "") . ") AND referencia IN (" . $referencias . ");";
        }
        // Descontarlos
        if(count($modificar)) {
            foreach($modificar as $mod) {
                $sql .= "UPDATE pendientesproveedores pdtprov JOIN (SELECT x.idpendiente,LEAST(x.cantidad, (@cum := @cum + x.cantidad) - " . $mod['cuantos'] . ") AS new_cantidad FROM (SELECT pdt.idpendiente,pdt.cantidad FROM pendientesproveedores pdt JOIN albaranesprov alb ON pdt.idalbaran = alb.idalbaran WHERE alb.codproveedor = " . $this->var2str($codproveedor) . ($codalmacen ? " AND alb.codalmacen = " . $this->var2str($codalmacen) : "") . " AND pdt.referencia = ". $this->var2str($mod['referencia']) ." ORDER BY alb.fecha ASC, alb.hora ASC LIMIT 18446744073709551615) x JOIN (SELECT @cum := 0) r) cum ON pdtprov.idpendiente = cum.idpendiente SET cantidad = new_cantidad;";
            }
            $sql .= "DELETE FROM " . $this->table_name . " WHERE cantidad <= 0;";
        }
        return $this->db->exec($sql);
    }

    public function get_articulo()
    {
        $articulo = new \articulo();
        return $articulo->get($this->referencia);
    }

    // Devuelve las fechas del albaran y del pedido si tiene
    public function get_fecha_y_almacen() {
        $albaran = new \albaran_proveedor();
        $albaran = $albaran->get($this->idalbaran);
        $almacen = new \almacen();
        $almacen = $almacen->get($albaran->codalmacen);
        return array("fecha"=>$albaran->fecha,"almacen"=>$almacen->nombre);
    }
}