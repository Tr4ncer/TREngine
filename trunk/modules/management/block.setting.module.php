<?php
if (!defined("TR_ENGINE_INDEX")) {
	require("../../engine/core/secure.class.php");
	new Core_Secure();
}

class Module_Management_Block extends Module_Model {
	public function setting() {
		Core_Loader::classLoader("Libs_Block");
		
		// Traitement
		$localView = Core_Request::getWord("localView");
		switch($localView) {
			case "moveup":
				$this->sendMove("up");
				break;
			case "movedown":
				$this->sendMove("down");
				break;
		}
		
		$content = "";
		if (Core_Main::isFullScreen()) {
			$content .= "<div id=\"block_main_setting\">";
		}
		// Affichage
		switch($localView) {
			case "edit":
				$content .= $this->tabEdit();
				break;
			default:
				$content .= $this->tabHome();
				break;
		}
		if (Core_Main::isFullScreen()) {
			$content .= "</div>";
		}
		return $content;
	}
	
	private function tabHome() {
		Core_Loader::classLoader("Libs_Rack");
		$firstLine = array(
			array(20, BLOCK_TYPE),
			array(35, BLOCK_TITLE),
			array(10, BLOCK_SIDE),
			array(5, BLOCK_POSITION),
			array(10, BLOCK_ACCESS),
			array(20, BLOCK_VIEW_MODULE_PAGE)
		);
		$rack = new Libs_Rack($firstLine);
		
		Core_Sql::select(
			Core_Table::$BLOCKS_TABLE, 
			array("block_id", "side", "position", "title", "content", "type", "rank", "mods"),
			array(),
			array("position")
		);
		if (Core_Sql::affectedRows() > 0) {
			while ($row = Core_Sql::fetchArray()) {
				// Parametre de la ligne
				$type = $row['type'];
				$title = "<a href=\"" . Core_Html::getLink("?mod=management&manage=block&localView=edit&blockId=" . $row['block_id']) . "\">" . $row['title'] . "</a>";
				$side = Libs_Block::getLitteralSide($row['side']);
				$position = Core_Html::getLinkForBlock("?mod=management&manage=block&localView=moveup&blockId=" . $row['block_id'],
					"?mod=management&manage=block&localView=moveup&blockId=" . $row['block_id'],
					"#block_main_setting",
					"^"
				);
				$position .= $row['position'];
				$position .= Core_Html::getLinkForBlock("?mod=management&manage=block&localView=movedown&blockId=" . $row['block_id'],
					"?mod=management&manage=block&localView=movedown&blockId=" . $row['block_id'],
					"#block_main_setting",
					"v"
				);
				$rank = Core_Access::getLitteralRank($row['rank']);
				$mods = ($row['mods'] == "all") ? BLOCK_ALL_PAGE : BLOCK_VARIES_PAGE;
				// Ajout de la ligne au tableau
				$rack->addLine(array($type, $title, $side, $position, $rank, $mods));
			}
		}
		return $rack->render();
	}
	
	private function sendMove($move) {
		$blockId = Core_Request::getInt("blockId", -1);
		
		if ($blockId > -1) { // Si l'id semble valide
			Core_Sql::select(
				Core_Table::$BLOCKS_TABLE,
				array("side", "position"),
				array("block_id = '" . $blockId . "'")
			);
			if (Core_Sql::affectedRows() > 0) { // Si le block existe
				$blockMove = Core_Sql::fetchArray(); // R�cuperation des informations sur le block
				
				if (($move != "up") || ($blockMove['position'] > 0 && $move == "up")) {
					// Requ�te de selection des autres blocks
					$where = "position = '" . $blockMove['position'] . "' OR position = '"
					. (($move == "up") ? ($blockMove['position'] - 1) : ($blockMove['position'] + 1)) . "'";
					Core_Sql::select(
						Core_Table::$BLOCKS_TABLE,
						array("block_id", "position"),
						array("side = '" . $blockMove['side'] . "' AND", $where)
					);
					if (Core_Sql::affectedRows() > 0) {
						// Mise � jour de position
						while ($row = Core_Sql::fetchArray()) {
							if ($move == "up") {
								$row['position'] = ($row['block_id'] == $blockId) ? $row['position'] - 1 : $row['position'] + 1;
							} else {
								$row['position'] = ($row['block_id'] == $blockId) ? $row['position'] + 1 : $row['position'] - 1;
							}
							// V�rification de la position la plus haute
							$row['position'] = ($row['position'] >= 0) ? $row['position'] : 0;
							Core_Sql::update(
								Core_Table::$BLOCKS_TABLE,
								array("position" => $row['position']),
								array("block_id = '" . $row['block_id'] . "'")
							);
						}
						Core_Exception::addInfoError(DATA_SAVED);
					}
				}
			}
		}
	}
	
	private function tabEdit() {
		$blockId = Core_Request::getInt("blockId", -1);
		
		if ($blockId > -1) { // Si l'id semble valide
			Core_Sql::select(
				Core_Table::$BLOCKS_TABLE,
				array("side", "position", "title", "content", "type", "rank", "mods"),
				array("block_id = '" . $blockId . "'")
			);
			if (Core_Sql::affectedRows() > 0) { // Si le block existe
				$block = Core_Sql::fetchArray();
				Core_Loader::classLoader("Libs_Form");
				
				$form = new Libs_Form("management-block-blockedit");
				$form->setTitle(BLOCK_EDIT_TITLE);
				$form->setDescription(BLOCK_EDIT_DESCRIPTION);
				$form->addSpace();
				
				$form->addHtmlInFieldset("ID : #" . $blockId);
				$form->addInputText("blockTitle", BLOCK_TITLE, $block['title']);
				
				$blockList = Libs_Block::listBlocks();
				$form->addSelectOpenTag("blockType", BLOCK_TYPE);
				$form->addSelectItemTag($block['type'], "", true);
				foreach($blockList as $blockType) {
					if ($blockType == $block['type']) continue;
					$form->addSelectItemTag($blockType);
				}
				$form->addSelectCloseTag();
				
				$sideList = Libs_Block::listSide();
				$form->addSelectOpenTag("blockSide", BLOCK_SIDE);
				$currentSideName = "";
				foreach($sideList as $blockSide) {
					if ($blockSide['numeric'] == $block['side']) {
						$currentSideName = $blockSide['letters'];
						continue;
					}
					$form->addSelectItemTag($blockSide['numeric'], $blockSide['numeric'] . " " . $blockSide['letters']);
				}
				$form->addSelectItemTag($block['side'], $block['side'] . " " . $currentSideName, true);
				$form->addSelectCloseTag();
				// TODO rafraichir la liste des ordres (position) suivant la liste des positions (side)
				$form->addInputText("blockTitle", BLOCK_POSITION, $block['position']);
				
				$rankList = Core_Access::listRanks();
				$form->addSelectOpenTag("blockRank", BLOCK_ACCESS);
				$currentRankName = "";
				foreach($rankList as $blockRank) {
					if ($blockRank['numeric'] == $block['rank']) {
						$currentRankName = $blockRank['letters'];
						continue;
					}
					$form->addSelectItemTag($blockRank['numeric'], $blockRank['numeric'] . " " . $blockRank['letters']);
				}
				$form->addSelectItemTag($block['rank'], $block['rank'] . " " . $currentRankName, true);
				$form->addSelectCloseTag();
				// TODO faire une liste cliquable avec un bouton radio "toutes les pages" et "aucune page" (= rank -1)
				$form->addInputText("blockTitle", BLOCK_VIEW_MODULE_PAGE, $block['mods']);
				
				$form->addInputText("blockTitle", "content", $block['content']);
				
				$position .= Core_Html::getLinkForBlock("?mod=management&manage=block&localView=movedown&blockId=" . $row['block_id'],
					"?mod=management&manage=block&localView=movedown&blockId=" . $row['block_id'],
					"#block_main_setting",
					"v"
				);
				return $form->render();
			}
		}
		return "";
	}
}

?>