<?php
/**
 * @version $Id: change.class.php $
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


class PluginPdfChange extends PluginPdfCommon {


   static $rightname = "plugin_pdf";


   function __construct(CommonGLPI $obj=NULL) {
      $this->obj = ($obj ? $obj : new Change());
   }


   static function pdfMain(PluginPdfSimplePDF $pdf, Change $job) {

      $dbu = new DbUtils();

      $ID = $job->getField('id');
      if (!$job->can($ID, READ)) {
         return false;
      }

      $pdf->setColumnsSize(100);

      $pdf->displayTitle('<b>'.
               (empty($job->fields["name"])?__('Without title'):$name=$job->fields["name"]).'</b>');

      if (count($_SESSION['glpiactiveentities'])>1) {
         $entity = " (".Dropdown::getDropdownName("glpi_entities", $job->fields["entities_id"]).")";
      } else {
         $entity = '';
      }

      $pdf->setColumnsSize(50,50);
      $recipient_name='';
      if ($job->fields["users_id_recipient"]) {
         $recipient      = new User();
         $recipient->getFromDB($job->fields["users_id_recipient"]);
         $recipient_name = $recipient->getName();
      }

      $sla = $due = $commentsla = '';
      if ($job->fields['time_to_resolve']) {
         $due = "<b><i>".sprintf(__('%1$s: %2$s'), __('Time to resolve')."</b></i>",
                                 Html::convDateTime($job->fields['time_to_resolve']));
      }
      $pdf->displayLine(
         "<b><i>".sprintf(__('%1$s: %2$s'), __('Opening date')."</i></b>",
                          Html::convDateTime($job->fields["date"])), $due);

      $pdf->setColumnsSize(50,50);
      $lastupdate = Html::convDateTime($job->fields["date_mod"]);
      if ($job->fields['users_id_lastupdater'] > 0) {
         $lastupdate = sprintf(__('%1$s by %2$s'), $lastupdate,
                               $dbu->getUserName($job->fields["users_id_lastupdater"]));
      }

      $pdf->displayLine(
         '<b><i>'.sprintf(__('%1$s: %2$s'), __('By')."</i></b>", $recipient_name),
         '<b><i>'.sprintf(__('%1$s: %2$s'), __('Last update').'</i></b>', $lastupdate));

      $status = '';
      if (in_array($job->fields["status"], $job->getSolvedStatusArray())
            || in_array($job->fields["status"], $job->getClosedStatusArray())) {
         $status = sprintf(__('%1$s %2$s'), '-', Html::convDateTime($job->fields["solvedate"]));
      }
      if (in_array($job->fields["status"], $job->getClosedStatusArray())) {
         $status = sprintf(__('%1$s %2$s'), '-', Html::convDateTime($job->fields["closedate"]));
      }

      if ($job->fields["status"] == Change::WAITING) {
         $status = sprintf(__('%1$s %2$s'), '-',
               Html::convDateTime($job->fields['begin_waiting_date']));
      }

      $pdf->displayLine(
         "<b><i>".sprintf(__('%1$s: %2$s'), __('Status')."</i></b>",
                          Html::clean($job->getStatus($job->fields["status"])). $status),
         "<b><i>".sprintf(__('%1$s: %2$s'), __('Urgency')."</i></b>",
                  Html::clean($job->getUrgencyName($job->fields["urgency"]))));


      $pdf->displayLine(
          "<b><i>".sprintf(__('%1$s: %2$s'), __('Category')."</i></b>",
                          Dropdown::getDropdownName("glpi_itilcategories",
                                                    $job->fields["itilcategories_id"])),
         "<b><i>". sprintf(__('%1$s: %2$s'), __('Impact')."</i></b>",
                  Html::clean($job->getImpactName($job->fields["impact"]))));

      $pdf->displayLine(
         "<b><i>".sprintf(__('%1$s: %2$s'), __('Total duration')."</i></b>",
                          Html::clean(CommonITILObject::getActionTime($job->fields["actiontime"]))),
          "<b><i>".sprintf(__('%1$s: %2$s'), __('Priority')."</i></b>",
                             Html::clean($job->getPriorityName($job->fields["priority"]))));

      $pdf->setColumnsSize(50,50);

      // Requester
      $users     = [];
      $listusers = '';
      $requester = '<b><i>'.sprintf(__('%1$s: %2$s')."</i></b>", __('Requester'), $listusers);
      foreach ($job->getUsers(CommonITILActor::REQUESTER) as $d) {
         if ($d['users_id']) {
            $tmp = Html::clean($dbu->getUserName($d['users_id']));
            if ($d['alternative_email']) {
               $tmp .= ' ('.$d['alternative_email'].')';
            }
         } else {
            $tmp = $d['alternative_email'];
         }
         $users[] = $tmp;
      }
      if (count($users)) {
         $listusers = implode(', ', $users);
      }
      $pdf->displayText($requester, $listusers, 1);

      $groups         = [];
      $listgroups     = '';
      $requestergroup = '<b><i>'.sprintf(__('%1$s: %2$s')."</i></b>", __('Requester group'),
                                         $listgroups);
      foreach ($job->getGroups(CommonITILActor::REQUESTER) as $d) {
         $groups[] = Dropdown::getDropdownName("glpi_groups", $d['groups_id']);
      }
      if (count($groups)) {
      $listgroups = implode(', ', $groups);
      }
      $pdf->displayText($requestergroup, $listgroups, 1);

      // Observer
      $users     = [];
      $listusers = '';
      $watcher   = '<b><i>'.sprintf(__('%1$s: %2$s')."</i></b>", __('Watcher'), $listusers);
      foreach ($job->getUsers(CommonITILActor::OBSERVER) as $d) {
         if ($d['users_id']) {
            $tmp = Html::clean($dbu->getUserName($d['users_id']));
            if ($d['alternative_email']) {
               $tmp .= ' ('.$d['alternative_email'].')';
            }
         } else {
            $tmp = $d['alternative_email'];
         }
         $users[] = $tmp;
      }
      if (count($users)) {
         $listusers = implode(', ', $users);
      }
      $pdf->displayText($watcher, $listusers, 1);

      $groups       = [];
      $listgroups   = '';
      $watchergroup = '<b><i>'.sprintf(__('%1$s: %2$s')."</i></b>", __('Watcher group'),
                                         $listgroups);
      foreach ($job->getGroups(CommonITILActor::OBSERVER) as $d) {
         $groups[] = Dropdown::getDropdownName("glpi_groups", $d['groups_id']);
      }
      if (count($groups)) {
         $listgroups = implode(', ', $groups);
      }
      $pdf->displayText($watchergroup, $listgroups, 1);

      // Assign to
      $users     = [];
      $listusers = '';
      $assign    = '<b><i>'.sprintf(__('%1$s: %2$s')."</i></b>", __('Assigned to technicians'),
                                    $listusers);
      foreach ($job->getUsers(CommonITILActor::ASSIGN) as $d) {
         if ($d['users_id']) {
            $tmp = Html::clean($dbu->getUserName($d['users_id']));
            if ($d['alternative_email']) {
               $tmp .= ' ('.$d['alternative_email'].')';
            }
         } else {
            $tmp = $d['alternative_email'];
         }
         $users[] = $tmp;
      }
      if (count($users)) {
         $listusers = implode(', ', $users);
      }
      $pdf->displayText($assign, $listusers, 1);

      $groups      = [];
      $listgroups  = '';
      $assigngroup = '<b><i>'.sprintf(__('%1$s: %2$s')."</i></b>", __('Assigned to groups'),
                                         $listgroups);
      foreach ($job->getGroups(CommonITILActor::ASSIGN) as $d) {
         $groups[] = Dropdown::getDropdownName("glpi_groups", $d['groups_id']);
      }
      if (count($groups)) {
         $listgroups = implode(', ', $groups);
      }
      $pdf->displayText($assigngroup, $listgroups, 1);

     // Supplier
      $suppliers      = [];
      $listsuppliers  = '';
      $assignsupplier = '<b><i>'.sprintf(__('%1$s: %2$s')."</i></b>", __('Assigned to a supplier'),
                                         $listsuppliers);
      foreach ($job->getSuppliers(CommonITILActor::ASSIGN) as $d) {
         $suppliers[] = Html::clean(Dropdown::getDropdownName("glpi_suppliers", $d['suppliers_id']));
      }
      if (count($suppliers)) {
         $listsuppliers = implode(', ', $suppliers);
      }
      $pdf->displayText($assignsupplier, $listsuppliers, 1);

      $pdf->setColumnsSize(100);
      $pdf->displayLine(
            "<b><i>".sprintf(__('%1$s: %2$s'), __('Title')."</i></b>", $job->fields["name"]));

      $pdf->displayText("<b><i>".sprintf(__('%1$s: %2$s')."</i></b>", __('Description'), ''),
                                         Html::clean($job->fields["content"]), 1);

      $pdf->displaySpace();
   }


   static function pdfAnalysis(PluginPdfSimplePDF $pdf, Change $job) {

      $pdf->setColumnsSize(100);
      $pdf->displayTitle("<b>".__('Analysis')."</b>");

      $pdf->setColumnsSize(10, 90);

      $pdf->displayText(sprintf(__('%1$s: %2$s'), "<b><i>".__('Impacts')."</i></b>",
                                $job->fields['impactcontent']));

      $pdf->displayText(sprintf(__('%1$s: %2$s'), "<b><i>".__('Control list')."</i></b>",
                                $job->fields['controlistcontent']));
   }


   static function pdfPlan(PluginPdfSimplePDF $pdf, Change $job) {

      $pdf->setColumnsSize(100);
      $pdf->displayTitle("<b>".__('Plans')."</b>");

      $pdf->setColumnsSize(10, 90);

      $pdf->displayText(sprintf(__('%1$s: %2$s'), "<b><i>".__('Deployment plan')."</i></b>",
                                $job->fields['rolloutplancontent']));

      $pdf->displayText(sprintf(__('%1$s: %2$s'), "<b><i>".__('Backup plan')."</i></b>",
                                $job->fields['backoutplancontent']));

      $pdf->displayText(sprintf(__('%1$s: %2$s'), "<b><i>".__('Checklist')."</i></b>",
                                $job->fields['checklistcontent']));
   }


   static function pdfStat(PluginPdfSimplePDF $pdf, Change $job) {

      $pdf->setColumnsSize(100);
      $pdf->displayTitle("<b>".__('Statistics')."</b>");

      $pdf->displayTitle("<b>"._n('Date', 'Dates', 2)."</b>");

      $pdf->setColumnsSize(50, 50);
      $pdf->displayLine(__('Opening date'), Html::convDateTime($job->fields['date']));
      $pdf->displayLine(__('Time to resolve'), Html::convDateTime($job->fields['time_to_resolve']));

      if (in_array($job->fields["status"], $job->getSolvedStatusArray())
          || in_array($job->fields["status"], $job->getClosedStatusArray())) {
         $pdf->displayLine(__('Resolution date'), Html::convDateTime($job->fields['solvedate']));
      }
      if (in_array($job->fields["status"], $job->getClosedStatusArray())) {
         $pdf->displayLine(__('Closing date'), Html::convDateTime($job->fields['closedate']));
      }

      $pdf->setColumnsSize(100);
      $pdf->displayTitle("<b>"._n('Time', 'Times', 2)."</b>");

      $pdf->setColumnsSize(50, 50);
      if (isset($job->fields['takeintoaccount_delay_stat']) > 0) {
         if ($job->fields['takeintoaccount_delay_stat'] > 0) {
            $accountdelay = Html::clean(Html::timestampToString($job->fields['takeintoaccount_delay_stat'],0));
         }
         $pdf->displayLine(__('Take into account'),
                           isset($accountdelay) ? $accountdelay : '');
      }

      if (in_array($job->fields["status"], $job->getSolvedStatusArray())
          || in_array($job->fields["status"], $job->getClosedStatusArray())) {
               if ($job->fields['solve_delay_stat'] > 0) {
            $pdf->displayLine(__('Resolution'),
                              Html::clean(Html::timestampToString($job->fields['solve_delay_stat'],0)));
         }
      }
      if (in_array($job->fields["status"], $job->getClosedStatusArray())) {
         if ($job->fields['close_delay_stat'] > 0) {
            $pdf->displayLine(__('Closing'),
                              Html::clean(Html::timestampToString($job->fields['close_delay_stat'],0)));
         }
      }
      if ($job->fields['waiting_duration'] > 0) {
         $pdf->displayLine(__('Pending'),
                           Html::clean(Html::timestampToString($job->fields['waiting_duration'],0)));
      }

      $pdf->displaySpace();
   }


   function defineAllTabs($options=[]) {

      $onglets = parent::defineAllTabs($options);
      unset($onglets['Itil_Project$1']);
      unset($onglets['Impact$1']);

      if (Session::haveRight('change', Change::READALL) // for technician
            || Session::haveRight('followup', ITILFollowup::SEEPRIVATE)
            || Session::haveRight('task', TicketTask::SEEPRIVATE)) {
         $onglets['_private_'] = __('Private');
      }

      return $onglets;
   }


   static function displayTabContentForPDF(PluginPdfSimplePDF $pdf, CommonGLPI $item, $tab) {

      $private = isset($_REQUEST['item']['_private_']);

      switch ($tab) {
         case '_private_' :
            // nothing to export, just a flag
            break;

         case 'Change$1' :
            self::pdfAnalysis($pdf, $item);
            break;

         case 'Change$3' :
            self::pdfPlan($pdf, $item);
            break;

         case 'Change$4' :
            self::pdfStat($pdf, $item);
            break;

         case 'Change$5' :
            PluginPdfItilFollowup::pdfForItem($pdf, $item,  $private);
            PluginPdfChangeTask::pdfForChange($pdf, $item,  $private);
            if (Session::haveRight('document', READ)) {
               PluginPdfDocument::pdfForItem($pdf, $item);
            }
            PluginPdfITILSolution::pdfForItem($pdf, $item);
            break;

         case 'ChangeValidation$1' :
            PluginPdfChangeValidation::pdfForChange($pdf, $item);
            break;

         case 'ChangeCost$1' :
            PluginPdfCommonItilCost::pdfForItem($pdf, $item);
            break;

         case 'Change_Problem$1' :
            PluginPdfChange_Problem::pdfForChange($pdf, $item);
            break;

         case 'Change_Ticket$1' :
            PluginPdfChange_Ticket::pdfForChange($pdf, $item);
            break;

         case 'Change_Item$1' :
            PluginPdfChange_Item::pdfForChange($pdf, $item);
            break;

         default :
            return false;
      }

      return true;
   }


}

