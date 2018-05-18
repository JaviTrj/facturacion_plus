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
class compras_pendientes_proveedor extends fs_controller
{

    public $codproveedor;
    public $proveedor_metodo;
    public $metodos_pendientes;
    public $multialmacen;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Pendientes de proveedor', '', FALSE, FALSE);
        $this->share_extensions();
    }

    protected function private_core()
    {
        
        if(isset($_GET['cod'])) {
            $this->codproveedor = $_GET['cod'];
            $almacen = new almacen();
            $this->multialmacen = count($almacen->all()) > 1;
            if(isset($_GET['ajax'])) {
                $this->devolver_pendientes_json(+$_GET['ajax']);
            }
            else {
                $op = new opcion_pendientes_proveedor();
                $this->metodos_pendientes = $op->all();

                // Cambiar el método de pendientes
                if(isset($_POST['metodo_pendientes'])) {
                    $nuevo_metodo = new proveedor_opcion_pendientes(array("codproveedor"=>$this->codproveedor,"codopcion"=>$_POST['metodo_pendientes']));
                    $nuevo_metodo->save();
                    $this->proveedor_metodo = $nuevo_metodo;
                }
                else {
                    $op_proveedor = new proveedor_opcion_pendientes();
                    $this->proveedor_metodo = $op_proveedor->get($this->codproveedor);
                }
            }
        } 
    }

    public function share_extensions()
    {
        $fsext = new fs_extension();
        $fsext->name = 'pendientes_proveedor';
        $fsext->from = __CLASS__;
        $fsext->to = 'compras_proveedor';
        $fsext->type = 'tab';
        $fsext->text = '<span class="glyphicon glyphicon-hourglass"></span> &nbsp; Pendientes';
        $fsext->save();
    }

    public function url() {
        return "index.php?page=compras_pendientes_proveedor&cod=" . $this->codproveedor;
    }

    public function devolver_pendientes_json($por_articulos) {
        $pendiente = new pendiente_proveedor();
        $pendientes = $pendiente->get_all_from_proveedor($this->codproveedor,$this->multialmacen);
        $results = array();
        if($pendientes) {
            foreach($pendientes as $pendiente) {
                $articulo = $pendiente->get_articulo();
                if($por_articulos) {
                    if(isset($results[$articulo->referencia])) {
                        $results[$articulo->referencia]['pendientes'] += $pendiente->cantidad;
                    }
                    else {
                        $results[$articulo->referencia] = array(
                            'referencia'=>$articulo->referencia,
                            'descripcion'=>$articulo->descripcion,
                            'pendientes'=>$pendiente->cantidad,
                            'coste'=>$articulo->preciocoste(),
                            'dto'=>0,
                            'codimpuesto'=>$articulo->codimpuesto
                        );

                        // Buscar artículo en el proveedor
                        $articulo_prov = new articulo_proveedor(); 
                        $ap = $articulo_prov->get_by($articulo->referencia, $this->codproveedor);
                        if ($ap) {
                            $results[$articulo->referencia]["coste"] = $ap->precio;
                            $results[$articulo->referencia]["dto"] = $ap->dto;
                        }
                    }
                }
                else {
                    $fecha_y_almacen = $pendiente->get_fecha_y_almacen();
                    $pdt = array(
                        $pendiente->referencia,
                        $articulo->descripcion,
                        $pendiente->idalbaran,
                        $fecha_y_almacen['fecha'],
                        $pendiente->cantidad,
                        $articulo->preciocoste(),
                        $articulo->pvp,
                        $articulo->preciocoste()*$pendiente->cantidad
                    );
                    if($this->multialmacen) {
                        array_splice($pdt,4,0,array($fecha_y_almacen['almacen']));
                    }
                    $results[] = $pdt;
                }
            }
        }
        /// desactivamos la plantilla HTML
        $this->template = FALSE;

        header('Content-Type: application/json');
        if($por_articulos) {
            $results = array_values($results);
        }
        echo json_encode($results);
    }
}
