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
class cargar_articulos extends fs_controller
{

    public $fabricantes;
    public $familias;
    public $impuestos;

    public $creados;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Cargar fichero de articulos', '', FALSE, FALSE);
        $this->share_extensions();
    }

    protected function private_core()
    {

        if(isset($_GET['ajax'])) {
            switch($_GET['ajax']) {
                case "nuevos_articulos":
                    $this->crear_articulos();
                    break;
                case "eliminar_articulos_creados":
                    $this->eliminar_articulos_creados();
                    break;
                default:
                    $this->new_error_msg("El origen de los valores no es un valido");
            }
        }
        else {
            $fabricante = new fabricante();
            $this->fabricantes = $fabricante->all();
            $familia = new familia();
            $this->familias = $familia->all();
            $impuesto = new impuesto();
            $this->impuestos = $impuesto->all();
        }
    }

    public function share_extensions()
    {
        $fsext = new fs_extension();
        $fsext->name = 'cargar_articulos';
        $fsext->from = __CLASS__;
        $fsext->to = 'ventas_articulos';
        $fsext->type = 'button';
        $fsext->text = '<span class="glyphicon glyphicon-import"></span> &nbsp; Cargar artículos desde fichero';
        $fsext->save();
    }

    public function url()
    {
        return "index.php?page=cargar_articulos";
    }

    function crear_articulos()
    {
        $this->core_log->clean_errors();
        $errors = array();
        $fails = 0;
        $creados = array("valores"=>array(),"articulos"=>array());
        $nuevos_valores = array();

        // Añadir nuevos valores a los atributos
        if(isset($_POST['data']['valores'])) {
            $nuevos_valores = $_POST['data']['valores'];
            foreach($nuevos_valores as $idatributo=>&$valores) {
                $valores = array_map(function($nombre) use($idatributo, &$creados) {
                    $valor_familia = new valor_atributo_familia();
                    $valor_familia->idatributofamilia = $idatributo;
                    $valor_familia->nombre = $nombre;
                    if($valor_familia->save()) {
                        $creados["valores"][$idatributo][] = $valor_familia->idvaloratributo;
                        return $valor_familia->idvaloratributo;
                    }
                    $errors[] = array("row"=>-1,"message"=>"No se pudo crear el valor ". $nombre);
                    return NULL;
                }, $valores);
            }
        }

        $data = $_POST['data']['articulos'];
        $articulo = new articulo();
        $new_referencia = intval($articulo->get_new_referencia());
        $articulos = array();
        foreach($data as $row=>$d) {
            $articulo = new articulo();
            $articulo->atributos = array();
            $tipo_precio = NULL;
            foreach($d as $name=>$value) {
                if($name == "atributos") {
                    foreach($value as $idatributo=>$valor) {
                        $valor = is_array($valor) ? $nuevos_valores[$idatributo][$valor['valor_encontrado']] : $valor;
                        $articulo->atributos[$idatributo] = $valor;
                    }
                }
                else {
                    if($name == "tipo_precio") {
                        $tipo_precio = $value;
                    }
                    else {
                        $articulo->{$name} = $value;
                    }
                }
            }
            // Ajustar el precio si viene con impuesto
            if(isset($tipo_precio) && $articulo->pvp > 0 && $tipo_precio == "CON") {
                $articulo->set_pvp_iva($articulo->pvp);
            }

            // Añade referencia si no tiene
            if(is_null($articulo->referencia)) {
                $articulo->referencia = strval($new_referencia);
                $new_referencia++;
            }

            if($articulo->test() && $articulo->test_atributos($articulo->atributos)) {
                $articulos[] = $articulo;
                $creados["articulos"][] = $articulo->referencia;
            }
            else {
                $message = count($this->get_errors()) ? $this->get_errors() : "Sin información";
                $errors[] = array("row"=>$row+1,"message"=>$message);
                $this->core_log->clean_errors();
                $fails++;
            }
        }
        if($articulo->save_many($articulos)) {
            $resultado = array("exitos"=>count($articulos),"fallos"=>$fails,"errores"=>$errors,"creados"=>$creados);
        }
        else {
            $errors[] = array("row"=>-1,"message"=>"Fallo al insertar el conjunto de artículos");
            $resultado = array("exitos"=>0,"fallos"=>count($data),"errores"=>$errors,"creados"=>$creados);
        }

        $this->template = FALSE;

        header('Content-Type: application/json');
        echo json_encode($resultado);
    }

    public function eliminar_articulos_creados() {
        $this->template = FALSE;

        $creados = $_POST["creados"];
        $valor = new valor_atributo_familia();
        
        $status = TRUE;

        if(isset($creados['valores'])) {
            foreach($creados['valores'] as $idvalor) {
                $valor = $valor->get($idvalor);
                if(!$valor || !$valor->delete()) {
                    $status = FALSE;
                }
            }
        }

        $articulo = new articulo();
        $status = $articulo->delete_many($creados['articulos']) && $status;

        if($status) {
            echo TRUE;
        }
        else {
            http_response_code(500);
            echo FALSE;
        }
    }
}