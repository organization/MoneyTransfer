<?php

namespace maru;

use pocketmine\plugin\PluginBase;
use ifteam\CustomPacket\CPAPI;
use pocketmine\event\Listener;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;
use onebone\economyapi\EconomyAPI;
use ifteam\CustomPacket\DataPacket;
use pocketmine\utils\Config;
use ifteam\CustomPacket\event\CustomPacketReceiveEvent;

class MoneyTransfer extends PluginBase implements Listener {
	/**
	 *
	 * @var EconomyAPI
	 */
	public $economy;
	public $serverlist;
	public function onEnable() {
		@mkdir($this->getDataFolder());
		$cp = $this->getServer ()->getPluginManager ()->getPlugin ( 'CustomPacket' );
		if (! $this->is_existPlugin ( $cp, 'CustomPacket' ))
			return;
		$this->economy = $this->getServer ()->getPluginManager ()->getPlugin ( 'EconomyAPI' );
		if (! $this->is_existPlugin ( $this->economy, 'EconomyAPI' ))
			return;
		$this->loadServerList();
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function is_existPlugin($plugin, $name) {
		if ($plugin == null) {
			$this->getLogger ()->error ( "{$name} 플러그인을 찾을 수 없습니다." );
			$this->getServer ()->getPluginManager ()->disablePlugin ( $this );
			return false;
		}
		return true;
	}
	public function onDisable() {
		$this->save();
	}
	public function loadServerList() {
		$this->serverlist = (new Config($this->getDataFolder()."serverlist.json", Config::JSON, [ ]))->getAll();
	}
	public function save() {
		$serverlist = new Config($this->getDataFolder()."serverlist.json");
		$serverlist->setAll($this->serverlist);
		$serverlist->save();
	}
	public function onCommand(CommandSender $sender, Command $command, $label, Array $args) {
		if (! isset ( $args [0] )) {
			return false;
		}
		switch ($args [0]) {
			case '이동' :
				if (! isset($args[1])) {
					$sender->sendMessage("사용법: /돈이동 이동 <서버명> <액수>");
					$sender->sendMessage("서버목록 보는법: /돈이동 서버목록");
					break;
				}
				if (!isset($args[2])) {
					$sender->sendMessage("사용법: /돈이동 이동 <서버명> <액수>");
					break;
				}
				if (!isset($this->serverlist[$args[1]])) {
					$sender->sendMessage("해당 서버를 찾을 수 없습니다.");
					$sender->sendMessage("서버목록 보는법: /돈이동 서버목록");
					break;
				}
				if (! is_numeric ( $amount = $args [2] )) {
					$sender->sendMessage ( TextFormat::RED . '금액은 숫자만 입력 가능합니다.' );
					break;
				}
				if ($this->economy->reduceMoney ( $sender, $amount ) !== EconomyAPI::RET_SUCCESS) {
					$lackmoney = $amount - $this->economy->myMoney ( $sender );
					$sender->sendMessage ( TextFormat::RED . "당신의 돈이 {$lackmoney}원 부족합니다." );
					break;
				}
				$data = json_encode(["MoneyTransfer", $sender->getName(), $amount]);
				$packet = new DataPacket($this->serverlist[$args[1]]['ip'], $this->serverlist[$args[1]]['port'], $data);
				CPAPI::sendPacket($packet);
				$sender->sendMessage("돈을 {$args[1]} 서버로 이동하였습니다.");
				break;
			case '서버추가' :
				if (!$sender->hasPermission("moneytransfer.cmd.addserver")) {
					$sender->sendMessage(TextFormat::RED."당신은 이 명령어를 실행할 권한이 없습니다.");
					break;
				}
				if (!isset ($args[1]) || !isset($args[2])) {
					$sender->sendMessage("사용법: /돈이동 서버추가 <서버명> <아이피[:포트]>");
					break;
				}
				$iport = explode(":", $args[2]);
				$ip = $iport[0];
				(isset($iport[1])) ? $port = $iport[1] : $port = 19132;
				$this->serverlist[$args[1]]['ip'] = $ip;
				$this->serverlist[$args[1]]['port'] = $port;
				$sender->sendMessage("{$args[1]} 서버를 추가하였습니다.");
				break;
			case '서버목록' :
				$count = 1;
				if (!is_array($this->serverlist)) {
					$sender->sendMessage("설정된 서버 목록이 없습니다. 관리자에게 문의하세요.");
					break;
				}
				$serverlist = "";
				foreach ($this->serverlist as $servername => $v1) {
					$serverlist .= $servername.'    ';
					if(++$count % 7 == 0) {
						$serverlist .= "\n";
					}
				}
				$sender->sendMessage("서버목록: ");
				$sender->sendMessage($serverlist);
				break;
			case '서버제거':
				if (!$sender->hasPermission("moneytransfer.cmd.deleteserver")) {
					$sender->sendMessage(TextFormat::RED."당신은 이 명령어를 실행할 권한이 없습니다.");
					break;
				}
				if (!isset($args[1])) {
					$sender->sendMessage("사용법: /돈이동 서버제거 <서버명>");
					return true;
				}
				if (!isset($this->serverlist[$args[1]])) {
					$sender->sendMessage("해당 서버가 목록에 존재하지 않습니다.");
					return true;
				}
				unset($this->serverlist[$args[1]]);
				$sender->sendMessage("{$args[1]} 서버를 서버목록에서 제거했습니다.");
				break;
			default :
				return false;
		}
		return true;
	}
	public function onDataPacketRecieve(CustomPacketReceiveEvent $event) {
		$this->getLogger()->debug("데이터 패킷을 전달받음.");
		$packet = $event->getPacket();
		$array = json_decode($packet->data);
		var_dump($array);
		if ($array[0] != "MoneyTransfer") {
			return;
		}
		$player = $array[1];
		$amount = $array[2];
		$this->economy->addMoney($player, $amount);
		$this->getLogger()->info("플레이어 {$player}가 {$amount}원을 다른서버에서 옮겨왔습니다.");
	}
}
?>