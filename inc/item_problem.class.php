<?php
/**
 * @version $Id: item_problem.class.php 558 2020-09-03 08:40:26Z yllen $
 -------------------------------------------------------------------------
 LICENSE

 This file is part of PDF plugin for GLPI.

 PDF is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.

 PDF is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with Reports. If not, see <http://www.gnu.org/licenses/>.

 @package   pdf
 @authors   Nelly Mahu-Lasson, Remi Collet
 @copyright Copyright (c) 2009-2020 PDF plugin team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://forge.glpi-project.org/projects/pdf
 @link      http://www.glpi-project.org/
 @since     2009
 --------------------------------------------------------------------------
*/


class PluginPdfItem_Problem extends PluginPdfCommon {


   static $rightname = "plugin_pdf";


   function __construct(CommonGLPI $obj=NULL) {
      $this->obj = ($obj ? $obj : new Item_Problem());
   }


   static function pdfForProblem(PluginPdfSimplePDF $pdf, Problem $problem) {
      global $DB;

      $dbu = new DbUtils();

      $instID = $problem->fields['id'];

      if (!$problem->can($instID, READ)) {
         return false;
      }

      $result = $DB->request('glpi_items_problems',
                             ['SELECT'    => 'itemtype',
                              'DISTINCT'  => true,
                              'WHERE'     => ['problems_id' => $instID],
                              'ORDER'     => 'itemtype']);

      $number = count($result);

      $pdf->setColumnsSize(100);
      $title = '<b>'._n('Item', 'Items', 2).'</b>';
      if (!$number) {
         $pdf->displayTitle(sprintf(__('%1$s: %2$s'), $title, __('No item to display')));
      } else {
         $title = sprintf(__('%1$s: %2$s'), $title, $number);
         $pdf->displayTitle($title);

         $pdf->setColumnsSize(20,20,26,17,17);
         $pdf->displayTitle("<i>".__('Type'), __('Name'), __('Entity'),__('Serial number'),
                                  __('Inventory number')."</i>");

                                        $totalnb = 0;
         for ($i=0 ; $i<$number ; $i++) {
            $row = $result->next();
            $itemtype = $row['itemtype'];
            if (!($item = $dbu->getItemForItemtype($itemtype))) {
               continue;
            }

            if ($item->canView()) {
               $itemtable = $dbu->getTableForItemType($itemtype);

               $query = "SELECT `$itemtable`.*,
                             `glpi_items_problems`.`id` AS IDD,
                             `glpi_entities`.`id` AS entity
                         FROM `glpi_items_problems`,
                              `$itemtable`";

            if ($itemtype != 'Entity') {
               $query .= " LEFT JOIN `glpi_entities`
                                 ON (`$itemtable`.`entities_id`=`glpi_entities`.`id`) ";
            }

            $query .= " WHERE `$itemtable`.`id` = `glpi_items_problems`.`items_id`
                              AND `glpi_items_problems`.`itemtype` = '$itemtype'
                              AND `glpi_items_problems`.`problems_id` = '$instID'";

            if ($item->maybeTemplate()) {
               $query .= " AND `$itemtable`.`is_template` = '0'";
            }

            $query .= $dbu->getEntitiesRestrictRequest(" AND", $itemtable, '', '',
                                                 $item->maybeRecursive())."
                      ORDER BY `glpi_entities`.`completename`, `$itemtable`.`name`";

            $result_linked = $DB->request($query);
            $nb            = count($result_linked);

            for ($prem=true ; $data=$result_linked->next() ; $prem=false) {
               $name = $data["name"];
               if (empty($data["name"])) {
                  $name = "(".$data["id"].")";
               }
               if ($prem) {
                  $typename = $item->getTypeName($nb);
                  $pdf->displayLine(Html::clean(sprintf(__('%1$s: %2$s'), $typename, $nb)),
                                    Html::clean($name),
                                    Dropdown::getDropdownName("glpi_entities", $data['entity']),
                                    Html::clean($data["serial"]),
                                    Html::clean($data["otherserial"]),$nb);
               } else {
                  $pdf->displayLine('',
                                    Html::clean($name),
                                    Dropdown::getDropdownName("glpi_entities", $data['entity']),
                                    Html::clean($data["serial"]),
                                    Html::clean($data["otherserial"]),$nb);
               }
            }
            $totalnb += $nb;
         }
         }
      }
      $pdf->displayLine("<b><i>".sprintf(__('%1$s = %2$s')."</b></i>", __('Total'), $totalnb));
   }


   static function pdfForItem(PluginPdfSimplePDF $pdf, CommonDBTM $item, $tree=false) {
      global $DB;

      $dbu = new DbUtils();

      $restrict         = '';
      $order            = '';
      switch ($item->getType()) {
         case 'User' :
            $restrict   = "(`glpi_problems_users`.`users_id` = '".$item->getID()."')";
            $order      = '`glpi_problems`.`date_mod` DESC';
            break;

         case 'Supplier' :
            $restrict   = "(`glpi_problems_suppliers`.`suppliers_id` = '".$item->getID()."')";
            $order      = '`glpi_problems`.`date_mod` DESC';
            break;

         case 'Group' :
            if ($tree) {
               $restrict = "IN (".implode(',', $dbu->getSonsOf('glpi_groups', $item->getID())).")";
            } else {
               $restrict = "='".$item->getID()."'";
            }
            $restrict   = "(`glpi_groups_problems`.`groups_id` $restrict)";
            $order      = '`glpi_problems`.`date_mod` DESC';
            break;

         default :
            $restrict   = "(`items_id` = '".$item->getID()."'
                            AND `itemtype` = '".$item->getType()."')";
            $order      = '`glpi_problems`.`date_mod` DESC';
            break;
      }

      $query = "SELECT ".Problem::getCommonSelect()."
                FROM `glpi_problems`
                LEFT JOIN `glpi_items_problems`
                  ON (`glpi_problems`.`id` = `glpi_items_problems`.`problems_id`) ".
                        Problem::getCommonLeftJoin()."
                WHERE $restrict ".
                      $dbu->getEntitiesRestrictRequest("AND","glpi_problems")."
                ORDER BY $order
                LIMIT ".intval($_SESSION['glpilist_limit']);

      $result = $DB->request($query);
      $number = count($result);

      $pdf->setColumnsSize(100);
      $title = '<b>'.Problem::getTypeName($number).'</b>';
      if (!$number) {
         $pdf->displayTitle(sprintf(__('%1$s: %2$s'), $title, __('No item to display')));
      } else {
         $pdf->displayTitle("<b>".sprintf(_n('Last %d problem','Last %d problems', $number)."</b>",
                                          $number));
      }

      $job = new Problem();
      while ($data = $result->next()) {
         if (!$job->getFromDB($data["id"])) {
            continue;
         }
         $pdf->setColumnsAlign('center');
         $col = '<b><i>ID '.$job->fields["id"].'</i></b>, '.
                       sprintf(__('%1$s: %2$s'), __('Status'),Problem::getStatus($job->fields["status"]));

         if (count($_SESSION["glpiactiveentities"]) > 1) {
            if ($job->fields['entities_id'] == 0) {
               $col = sprintf(__('%1$s (%2$s)'), $col, __('Root entity'));
            } else {
               $col = sprintf(__('%1$s (%2$s)'), $col,
                              Dropdown::getDropdownName("glpi_entities", $job->fields['entities_id']));
            }
         }
         $pdf->displayLine($col);

         $pdf->setColumnsAlign('left');

         $col = '<b><i>'.sprintf(__('Opened on %s').'</i></b>',
                                 Html::convDateTime($job->fields['date']));
         if ($job->fields['begin_waiting_date']) {
            $col = sprintf(__('%1$s, %2$s'), $col,
                           '<b><i>'.sprintf(__('Put on hold on %s').'</i></b>',
                                            Html::convDateTime($job->fields['begin_waiting_date'])));
         }
         if (in_array($job->fields["status"], $job->getSolvedStatusArray())
             || in_array($job->fields["status"], $job->getClosedStatusArray())) {

            $col = sprintf(__('%1$s, %2$s'), $col,
                           '<b><i>'.sprintf(__('Solved on %s').'</i></b>',
                                            Html::convDateTime($job->fields['solvedate'])));
         }
         if (in_array($job->fields["status"], $job->getClosedStatusArray())) {
            $col = sprintf(__('%1$s, %2$s'), $col,
                           '<b><i>'.sprintf(__('Closed on %s').'</i></b>',
                                            Html::convDateTime($job->fields['closedate'])));
         }
         if ($job->fields['time_to_resolve']) {
            $col = sprintf(__('%1$s, %2$s'), $col,
                           '<b><i>'.sprintf(__('%1$s: %2$s').'</i></b>', __('Time to resolve'),
                                            Html::convDateTime($job->fields['time_to_resolve'])));
         }
         $pdf->displayLine($col);

         $col = '<b><i>'.sprintf(__('%1$s: %2$s'), __('Priority').'</i></b>',
                                 Problem::getPriorityName($job->fields["priority"]));
         if ($job->fields["itilcategories_id"]) {
            $cat = '<b><i>'.sprintf(__('%1$s: %2$s'), __('Category').'</i></b>',
                                    Dropdown::getDropdownName('glpi_itilcategories',
                                                              $job->fields["itilcategories_id"]));
            $col = sprintf(__('%1$s - %2$s'), $col, $cat);
         }
         $pdf->displayLine($col);

         $lastupdate = Html::convDateTime($job->fields["date_mod"]);
         if ($job->fields['users_id_lastupdater'] > 0) {
            $lastupdate = sprintf(__('%1$s by %2$s'), $lastupdate,
                                  $dbu->getUserName($job->fields["users_id_lastupdater"]));
         }

         $pdf->displayLine('<b><i>'.sprintf(__('%1$s: %2$s'), __('Last update').'</i></b>',
               $lastupdate));

         $col   = '';
         $users = $job->getUsers(CommonITILActor::REQUESTER);
         if (count($users)) {
            foreach ($users as $d) {
               if (empty($col)) {
                  $col = $dbu->getUserName($d['users_id']);
               } else {
                  $col = sprintf(__('%1$s, %2$s'), $col, $dbu->getUserName($d['users_id']));
               }
            }
         }
         $grps = $job->getGroups(CommonITILActor::REQUESTER);
         if (count($grps)) {
            if (empty($col)) {
               $col = sprintf(__('%1$s %2$s'), $col, _n('Group', 'Groups', 2).' </i></b>');
            } else {
               $col = sprintf(__('%1$s - %2$s'), $col, _n('Group', 'Groups', 2).' </i></b>');
            }
            $first = true;
            foreach ($grps as $d) {
               if ($first) {
                  $col = sprintf(__('%1$s  %2$s'), $col,
                        Dropdown::getDropdownName("glpi_groups", $d['groups_id']));
               } else {
                  $col = sprintf(__('%1$s, %2$s'), $col,
                        Dropdown::getDropdownName("glpi_groups", $d['groups_id']));
               }
               $first = false;
            }
         }
         if ($col) {
            $texte = '<b><i>'.sprintf(__('%1$s: %2$s'), __('Requester').'</i></b>', '');
            $pdf->displayText($texte, $col, 1);
         }

         $col   = '';
         $users = $job->getUsers(CommonITILActor::ASSIGN);
         if (count($users)) {
            foreach ($users as $d) {
               if (empty($col)) {
                  $col = $dbu->getUserName($d['users_id']);
               } else {
                  $col = sprintf(__('%1$s, %2$s'), $col, $dbu->getUserName($d['users_id']));
               }
            }
         }
         $grps = $job->getGroups(CommonITILActor::ASSIGN);
         if (count($grps)) {
            if (empty($col)) {
               $col = sprintf(__('%1$s %2$s'), $col, _n('Group', 'Groups', 2).' </i></b>');
            } else {
               $col = sprintf(__('%1$s - %2$s'), $col, _n('Group', 'Groups', 2).' </i></b>');
            }
            $first = true;
            foreach ($grps as $d) {
               if ($first) {
                  $col = sprintf(__('%1$s  %2$s'), $col,
                        Dropdown::getDropdownName("glpi_groups", $d['groups_id']));
               } else {
                  $col = sprintf(__('%1$s, %2$s'), $col,
                        Dropdown::getDropdownName("glpi_groups", $d['groups_id']));
               }
               $first = false;
            }
         }
         if ($col) {
            $texte = '<b><i>'.sprintf(__('%1$s: %2$s').'</i></b>', __('Assigned to'), '');
            $pdf->displayText($texte, $col, 1);
         }

         $texte = '<b><i>'.sprintf(__('%1$s: %2$s').'</i></b>', __('Associated items'), '');

         $texte = '<b><i>'.sprintf(__('%1$s: %2$s').'</i></b>', __('Title'), '');
         $pdf->displayText($texte, $job->fields["name"], 1);
      }
      $pdf->displaySpace();
   }
}