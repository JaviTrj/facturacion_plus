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
class atributos_familia extends fs_controller
{
    public $atributos;
    public $codfamilia;
    public $num_articulos;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Atributos de familia', '', FALSE, FALSE);
        $this->share_extensions();
    }

    protected function private_core()
    {
        if(isset($_REQUEST['cod'])) {
            $this->codfamilia = $_REQUEST['cod'];

            if(isset($_GET["ajax"])) {
                switch($_GET["ajax"]) {
                    case "attr":
                        $this->devolver_atributos_de_familia();
                        break;
                    case "valores":
                        $this->devolver_valores_activos();
                        break;
                    case "diff":
                        $this->devolver_diferencia_atributos();
                        break;
                }
                return;  
            }
            if(isset($_POST['tipo'])) {
                $this->add_atributo();
                return;
            }
            if(isset($_GET['modify'])) {
                $this->modificar_atributo();
                return;
            }
            if(isset($_GET['delete'])) {
                $this->eliminar_atributo();
                return;
            }

            $atributo_de_familia = new atributo_familia();
            $this->atributos = $atributo_de_familia->get_atributos_de_familia($_GET['cod'],TRUE);
            $articulo = new articulo();
            $this->num_articulos = count($articulo->get_referencia_de_articulos_de_familia($this->codfamilia));
        }
    }

    public function share_extensions()
    {
        $fsext = new fs_extension();
        $fsext->name = 'atributos_en_familia';
        $fsext->from = __CLASS__;
        $fsext->to = 'ventas_familia';
        $fsext->type = 'tab';
        $fsext->text = 'Atributos';
        $fsext->save();
    }

    public function url()
    {
        return "index.php?page=atributos_familia&cod=".$this->codfamilia;
    }

    public function parent_url()
    {
        return "index.php?page=ventas_familia&cod=".$this->codfamilia;
    }

    public function add_atributo()
    {
        $_POST['codfamilia']  = $this->codfamilia;
        $atributo = new atributo_familia($_POST);
        $valores = array();
        if($atributo->save()) {
            // Añadir los valorse del atributo
            $i = 0;
            while(TRUE) {
                if(isset($_POST['atributo_valor_nombre'.$i])) {
                    $valor = new valor_atributo_familia(array("idatributofamilia"=>$atributo->idatributofamilia,"nombre"=>$_POST['atributo_valor_nombre'.$i]));
                    $valor->save();
                    $valores[] = $valor;
                }
                else {
                    break;
                }
                $i++;
            }
            // Si hay articulos y un valor por defecto, crear los registros necesarios
            if(isset($_POST['pordefecto']) && strlen($_POST['pordefecto'])) {
                $pordefecto = $_POST['pordefecto'];
                if($_POST['tipo'] == 'values') {
                    $pordefecto = substr($pordefecto, 21);
                    $pordefecto = $valores[$pordefecto]->idvaloratributo;
                }

                $atributo->set_atributo_articulos($pordefecto);
            }
        }

        $this->template = FALSE;

        header('Content-Type: text');

        echo "";
    }

    public function modificar_atributo()
    {
        $this->template = FALSE;

        header('Content-Type: text');

        $atributo = new atributo_familia();
        $atributo = $atributo->get($_GET["modify"]);

        $atributo->nombre = $_POST["nombre"];
        $quitar_nulos = FALSE;
        if($atributo->permitirnulo && !isset($_POST["permitirnulo"])) {
            $quitar_nulos = TRUE;
        }
        $atributo->permitirnulo = isset($_POST["permitirnulo"]);


        $valores = array("old"=>array(),"new"=>array());
        $defecto = NULL;
        if($atributo->tipo == 2) {
            // Obtenemos la posición del por defecto
            $matches = array();
            preg_match("/atributo_valor_nombre(\d+)(_\d+)?/", $_POST['pordefecto'], $matches);
            $orden_defecto = isset($matches[1]) ? $matches[1] : NULL;

            foreach($_POST as $key=>$param) {
                // Separamos los antiguos de los nuevos según si tienen el id del valor al final del key
                if(preg_match("/atributo_valor_nombre(\d+)(_(\d+))?/", $key, $matches)) {
                    $valor = array(
                        "orden"=>$matches[1],
                        "nombre"=>$param
                    );
                    if(isset($matches[2])) {
                        $valor["idvalor"] = $matches[3];
                        $valores["old"][] = $valor;

                        if($valor["orden"] == $orden_defecto) {
                            $defecto = $valor["idvalor"];
                        }
                    }
                    else {
                        $valores["new"][] = $valor;
                        
                        if($valor["orden"] == $orden_defecto) {
                            $defecto = -1;
                        }
                    }
                } 
            }
        }
        else if($quitar_nulos) {
            $defecto = $_POST['pordefecto'];
        }

        if(is_null($defecto) && ($quitar_nulos || ($atributo->tipo === 2 && !$atributo->permitirnulo))) {
            http_response_code(400);
            echo "Se ha solicitado poner el atributo a nulo cuando no lo permite";
            return;
        }

        if(!$atributo->save()) {
            http_response_code(500);
            echo "Se ha producido un error al guardar el artículo";
            return;
        }

        if($atributo->tipo == 2) {
            $valor = new valor_atributo_familia();

            // Modificar valores
            foreach($valores["old"] as $modificando) {
                $valor = $valor->get($modificando["idvalor"]);
                $valor->nombre = $modificando["nombre"];
                $valor->orden = $modificando["orden"];
                $valor->save();
            }

            $old_valores = $valor->get_valores_de_atributo($atributo->idatributofamilia);
            // Añadir nuevos
            foreach($valores["new"] as $modificando) {
                $valor = new valor_atributo_familia();
                $valor->idatributofamilia = $atributo->idatributofamilia;
                $valor->nombre = $modificando["nombre"];
                $valor->orden = $modificando["orden"];
                $valor->save();

                if($defecto === -1 && $valor->orden == $orden_defecto) {
                    $defecto = $valor->idvaloratributo;
                }
            }

            // Buscamos si alguno viejo ya no existe para eliminarlo
            foreach($old_valores as $old) {
                if(is_null(array_find($valores["old"], function($v) use($old) { return $v["idvalor"] == $old->idvaloratributo; }))) {
                    if($old->cambiar_articulos_a($defecto)) {
                        $old->delete();
                    }
                }
            }
        }

        // Si antes se permitian nulos y ahora no, pasar todos los artículos con nulo al por defecto
        if($quitar_nulos) {
            $atributo->set_atributo_articulos($defecto, NULL, TRUE);
        }

        echo "";
    }

    protected function eliminar_atributo()
    {
        $this->template = FALSE;

        header('Content-Type: text');

        $atributo = new atributo_familia();
        $atributo = $atributo->get($_GET['delete']);
        if($atributo && !$atributo->delete()) {
            http_response_code(500);
            echo "Se produjo un error al eliminar el atributo.";
        }

        echo "";
    }

    public function devolver_diferencia_atributos()
    {
        $atributo = new atributo_familia();
        $diff = $atributo->diff_atributos_cambio_familia($_GET['new'], $this->codfamilia, TRUE);

        $this->template = FALSE;

        header('Content-Type: application/json');
        echo json_encode($diff);
    }

    public function devolver_atributos_de_familia()
    {
        $atr = new atributo_familia();
        $atributos = array_map(function($v) {
            return array(
                "id"=>$v->idatributofamilia,
                "nombre"=>$v->nombre,
                "tipo"=>$v->tipo,
                "nulos"=>$v->permitirnulo,
                "valores"=>array_map(function($val) {
                        return array("cod"=>$val->idvaloratributo,"nombre"=>$val->nombre);
                    }, isset($v->valores) ? $v->valores : array())
            );
        }, $atr->get_atributos_de_familia($this->codfamilia, TRUE));


        /// desactivamos la plantilla HTML
        $this->template = FALSE;

        header('Content-Type: application/json');
        echo json_encode($atributos);
    }

    public function devolver_valores_activos()
    {
        $articulo = new articulo();
        $atributo = new atributo_familia();
        $atributos = crear_objeto_filtraje_por_atributos_familia();
        $valores = $atributo->valores_activos($_REQUEST['codfamilia'], $atributos, $articulo->search_con_atributos($this->query, 0, $_REQUEST['codfamilia'], isset($_REQUEST['con_stock']), $_REQUEST['codfabricante'], FALSE, isset($_REQUEST['solo_proveedor']) ? $_REQUEST['codproveedor'] : '', $atributos, TRUE));

        /// desactivamos la plantilla HTML
        $this->template = FALSE;

        header('Content-Type: application/json');
        echo json_encode($valores);
    }
}