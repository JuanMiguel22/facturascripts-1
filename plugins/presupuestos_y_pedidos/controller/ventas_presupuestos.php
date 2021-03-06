<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2014  Carlos Garcia Gomez  neorazorx@gmail.com
 * Copyright (C) 2014  Francesc Pineda Segarra  shawe.ewahs@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_model('agente.php');
require_model('articulo.php');
require_model('cliente.php');
require_model('fs_extension.php');
require_model('presupuesto_cliente.php');

class ventas_presupuestos extends fs_controller
{
   public $buscar_lineas;
   public $lineas;
   public $resultados;
   public $offset;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, ucfirst(FS_PRESUPUESTOS).' de cliente', 'ventas', FALSE, TRUE, TRUE);
   }
   
   protected function process()
   {
      $presupuesto = new presupuesto_cliente();
      
      $this->offset = 0;
      if( isset($_GET['offset']) )
         $this->offset = intval($_GET['offset']);
      
      if( isset($_POST['buscar_lineas']) )
      {
         $this->buscar_lineas();
      }
      else if( isset($_GET['codagente']) )
      {
         $this->template = 'extension/ventas_presupuestos_agente';
         $this->ppage = clone $this->page;
         $this->page->show_on_menu = FALSE;
         $this->page->title = 'Filtro: agente';
         
         $agente = new agente();
         $this->agente = $agente->get($_GET['codagente']);
         $this->resultados = $presupuesto->all_from_agente($_GET['codagente'], $this->offset);
      }
      else if( isset($_GET['codcliente']) )
      {
         $this->template = 'extension/ventas_presupuestos_cliente';
         $this->ppage = clone $this->page;
         $this->page->show_on_menu = FALSE;
         $this->page->title = 'Filtro: cliente';
         
         $cliente = new cliente();
         $this->cliente = $cliente->get($_GET['codcliente']);
         $this->resultados = $presupuesto->all_from_cliente($_GET['codcliente'], $this->offset);
      }
      else if( isset($_GET['ref']) )
      {
         $this->template = 'extension/ventas_presupuestos_articulo';
         $this->ppage = clone $this->page;
         $this->page->show_on_menu = FALSE;
         $this->page->title = 'Filtro: artículo';
         
         $articulo = new articulo();
         $this->articulo = $articulo->get($_GET['ref']);
         
         $linea = new linea_presupuesto_cliente();
         $this->resultados = $linea->all_from_articulo($_GET['ref'], $this->offset);
      }
      else
      {
         $this->custom_search = TRUE;
         $this->share_extension();
         
         $this->buttons[] = new fs_button('b_nuevo_presupuesto', 'Nuevo', 'index.php?page=nueva_venta&tipo=presupuesto');
         $this->buttons[] = new fs_button('b_buscar_lineas', 'Lineas');
         
         if( isset($_POST['delete']) )
         {
            $this->delete_presupuesto();
         }
         
         if($this->query)
         {
            $this->resultados = $presupuesto->search($this->query, $this->offset);
         }
         else if( isset($_GET['pendientes']) )
         {
            $this->resultados = $presupuesto->all_ptepedir($this->offset);
         }
         else
            $this->resultados = $presupuesto->all($this->offset);
      }
   }
   
   public function anterior_url()
   {
      $url = '';
      $extra = '';
      
      if( isset($_GET['ptepedido']) )
      {
         $extra = '&ptepedido=TRUE';
      }
      else if( isset($_GET['codagente']) )
      {
         $extra = '&codagente='.$_GET['codagente'];
      }
      else if( isset($_GET['codcliente']) )
      {
         $extra = '&codcliente='.$_GET['codcliente'];
      }
      else if( isset($_GET['ref']) )
      {
         $extra = '&ref='.$_GET['ref'];
      }
      
      if($this->query!='' AND $this->offset>'0')
      {
         $url = $this->url()."&query=".$this->query."&offset=".($this->offset-FS_ITEM_LIMIT).$extra;
      }
      else if($this->query=='' AND $this->offset>'0')
      {
         $url = $this->url()."&offset=".($this->offset-FS_ITEM_LIMIT).$extra;
      }
      
      return $url;
   }
   
   public function siguiente_url()
   {
      $url = '';
      $extra = '';
      
      if( isset($_GET['ptepedido']) )
      {
         $extra = '&ptepedido=TRUE';
      }
      else if( isset($_GET['codagente']) )
      {
         $extra = '&codagente='.$_GET['codagente'];
      }
      else if( isset($_GET['codcliente']) )
      {
         $extra = '&codcliente='.$_GET['codcliente'];
      }
      else if( isset($_GET['ref']) )
      {
         $extra = '&ref='.$_GET['ref'];
      }
      
      if($this->query!='' AND count($this->resultados)==FS_ITEM_LIMIT)
      {
         $url = $this->url()."&query=".$this->query."&offset=".($this->offset+FS_ITEM_LIMIT).$extra;
      }
      else if($this->query=='' AND count($this->resultados)==FS_ITEM_LIMIT)
      {
         $url = $this->url()."&offset=".($this->offset+FS_ITEM_LIMIT).$extra;
      }
      
      return $url;
   }
   
   public function buscar_lineas()
   {
      /// cambiamos la plantilla HTML
      $this->template = 'ajax/ventas_lineas_presupuestos';
      
      $this->buscar_lineas = $_POST['buscar_lineas'];
      $linea = new linea_presupuesto_cliente();
      $this->lineas = $linea->search($this->buscar_lineas);
   }
   
   private function delete_presupuesto()
   {
      $pre1 = new presupuesto_cliente();
      $pre1 = $pre1->get($_POST['delete']);
      if($pre1)
      {
         /// ¿Actualizamos el stock de los artículos?
         if( isset($_POST['stock']) )
         {
            $articulo = new articulo();
            
            foreach($pre1->get_lineas() as $linea)
            {
               $art0 = $articulo->get($linea->referencia);
               if($art0)
               {
                  $art0->sum_stock($pre1->codalmacen, $linea->cantidad);
                  $art0->save();
               }
            }
         }
         
         if( $pre1->delete() )
         {
            $this->new_message(ucfirst(FS_PRESUPUESTO)." ".$pre1->codigo." borrado correctamente.");
         }
         else
            $this->new_error_msg("¡Imposible borrar el ".FS_PRESUPUESTO."!");
      }
      else
         $this->new_error_msg("¡".ucfirst(FS_PRESUPUESTO)." no encontrado!");
   }
   
   private function share_extension()
   {
      /// cargamos la extensión para clientes
      $fsext0 = new fs_extension();
      if( !$fsext0->get_by(__CLASS__, 'ventas_cliente') )
      {
         $fsext = new fs_extension();
         $fsext->from = __CLASS__;
         $fsext->to = 'ventas_cliente';
         $fsext->type = 'button';
         $fsext->text = ucfirst(FS_PRESUPUESTOS);
         $fsext->save();
      }
      
      if( !$fsext0->get_by(__CLASS__, 'admin_agente') )
      {
         $fsext = new fs_extension();
         $fsext->from = __CLASS__;
         $fsext->to = 'admin_agente';
         $fsext->type = 'button';
         $fsext->text = ucfirst(FS_PRESUPUESTOS).' de clientes';
         $fsext->save();
      }
      
      if( !$fsext0->get_by(__CLASS__, 'ventas_articulo') )
      {
         $fsext = new fs_extension();
         $fsext->from = __CLASS__;
         $fsext->to = 'ventas_articulo';
         $fsext->type = 'button';
         $fsext->text = ucfirst(FS_PRESUPUESTOS).' de clientes';
         $fsext->save();
      }
   }
}
