<?php
declare(strict_types=1);

namespace MysteryBox;

use MysteryBox\box\MysteryBox as BoxMystery;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\Position;

class MysteryBox extends PluginBase implements Listener{
	use SingletonTrait;

	protected string $prefix = "§d<§f시스템§d> §f";

	/** @var string[] */
	protected array $pos = [];

	/** @var BoxMystery[] */
	protected array $box = [];

	protected array $createQueue = [];

	protected function onLoad() : void{
		self::$instance = $this;
	}

	protected function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$keyConfig = new Config($this->getDataFolder() . "Config.json", Config::JSON, []);
		$keyConfig->save();
		if(!file_exists($this->getDataFolder() . "BoxData.json")){
			file_put_contents($this->getDataFolder() . "BoxData.json", json_encode([]));
		}
		if(!file_exists($this->getDataFolder() . "PosData.json")){
			file_put_contents($this->getDataFolder() . "PosData.json", json_encode([]));
		}
		$boxData = json_decode(file_get_contents($this->getDataFolder() . "BoxData.json"), true);
		foreach($boxData as $name => $datum){
			$box = BoxMystery::jsonDeserialize($datum);
			$this->box[$box->getName()] = $box;
		}
		$posData = json_decode(file_get_contents($this->getDataFolder() . "PosData.json"), true);
		foreach($posData as $pos => $name){
			$this->pos[$pos] = $name;
		}
	}

	protected function onDisable() : void{
		$arr = [];
		foreach($this->box as $name => $box){
			$arr[] = $box->jsonSerialize();
		}
		file_put_contents($this->getDataFolder() . "BoxData.json", json_encode($arr));
		$arr = [];
		foreach($this->pos as $pos => $name){
			$arr[$pos] = $name;
		}
		file_put_contents($this->getDataFolder() . "PosData.json", json_encode($arr));
	}

	/**
	 * @param string $name
	 *
	 * @return BoxMystery|null
	 */
	public function getBoxByName(string $name) : ?BoxMystery{
		return $this->box[$name] ?? null;
	}

	/**
	 * @param Position $pos
	 *
	 * @return BoxMystery|null
	 */
	public function getBoxByPosition(Position $pos) : ?BoxMystery{
		foreach($this->pos as $position => $name){
			$a = explode(":", $position);
			$position = new Position((float) $a[0], (float) $a[1], (float) $a[2], $this->getServer()->getWorldManager()->getWorldByName($a[3]));
			if($position->equals($pos)){
				return $this->getBoxByName($name);
			}
		}
		return null;
	}

	public function getKeyByName(string $key) : ?Item{
		$config = new Config($this->getDataFolder() . "Config.json", Config::JSON, []);
		if($config->getNested($key, null) !== null){
			return Item::jsonDeserialize($config->getNested($key));
		}
		return null;
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if($command->getName() === "뽑기관리"){
			if(!$sender instanceof Player)
				return true;
			switch($args[0] ?? "x"){
				case "뽑기생성":
					array_shift($args);
					$name = array_shift($args);
					$key = array_shift($args);
					if(!isset($name)){
						$sender->sendMessage($this->prefix . "뽑기 이름을 입력해주세요.");
						break;
					}
					if($this->getBoxByName($name) instanceof BoxMystery){
						$sender->sendMessage($this->prefix . "해당 이름의 뽑기가 이미 존재합니다.");
						break;
					}
					if(!isset($key)){
						$sender->sendMessage($this->prefix . "뽑기에 필요한 열쇠를 입력해주세요.");
						break;
					}
					if(!$this->getKeyByName($key) instanceof Item){
						$sender->sendMessage($this->prefix . "해당 열쇠는 등록되어있지 않습니다.");
						break;
					}
					$box = new BoxMystery($name, [], $key);
					$this->box[$name] = $box;
					$sender->sendMessage($this->prefix . $name . " 뽑기를 추가하였습니다.");
					break;
				case "뽑기제거":
					array_shift($args);
					$name = array_shift($args);
					if(!isset($name)){
						$sender->sendMessage($this->prefix . "뽑기 이름을 입력해주세요.");
						break;
					}
					if(!$this->getBoxByName($name) instanceof BoxMystery){
						$sender->sendMessage($this->prefix . "해당 이름의 뽑기가 존재하지 않습니다.");
						break;
					}
					unset($this->box[$name]);
					$sender->sendMessage($this->prefix . $name . " 뽑기를 제거하였습니다.");
					break;
				case "아이템추가":
					array_shift($args);
					$name = array_shift($args);
					$per = array_shift($args);
					if(!isset($name)){
						$sender->sendMessage($this->prefix . "뽑기 이름을 입력해주세요.");
						break;
					}
					if(!$this->getBoxByName($name) instanceof BoxMystery){
						$sender->sendMessage($this->prefix . "해당 이름의 뽑기가 존재하지 않습니다.");
						break;
					}
					if(!isset($per) or !is_numeric($per)){
						$sender->sendMessage($this->prefix . "뽑기의 확률을 입력해주세요.(숫자가 높을수록 잘뽑힘)");
						break;
					}
					$box = $this->getBoxByName($name);
					$item = $sender->getInventory()->getItemInHand();
					if($item->getId() === 0){
						$sender->sendMessage($this->prefix . "아이템의 아이디는 공기가 아니어야 합니다.");
						break;
					}
					$box->addItem($item, (int) $per);
					$sender->sendMessage(sprintf($this->prefix . "%s 을(를) %s 박스에 추가하였습니다.", $item->getName(), $box->getName()));
					break;
				case "열쇠추가":
					array_shift($args);
					$key = array_shift($args);
					if(!isset($key)){
						$sender->sendMessage($this->prefix . "열쇠의 이름을 입력해주세요.");
						break;
					}
					if($this->getKeyByName($key) instanceof Item){
						$sender->sendMessage($this->prefix . "해당 이름의 열쇠가 이미 존재합니다.");
						break;
					}
					$item = $sender->getInventory()->getItemInHand();
					if($item->getId() === 0){
						$sender->sendMessage($this->prefix . "아이템의 아이디는 공기가 아니어야 합니다.");
						break;
					}
					$config = new Config($this->getDataFolder() . "Config.json", Config::JSON, []);
					$config->setNested($key, $item->jsonSerialize());
					$config->save();
					$sender->sendMessage(sprintf($this->prefix . "%s 열쇠를 추가했습니다.", $key));
					break;
				case "생성":
					array_shift($args);
					$name = array_shift($args);
					if(!isset($name)){
						$sender->sendMessage($this->prefix . "뽑기 이름을 입력해주세요.");
						break;
					}
					if(!$this->getBoxByName($name) instanceof BoxMystery){
						$sender->sendMessage($this->prefix . "해당 이름의 뽑기가 존재하지 않습니다.");
						break;
					}
					$this->createQueue[$sender->getName()] = $name;
					$sender->sendMessage($this->prefix . "생성할 블럭을 터치해주세요.");
					break;
				case "목록":
					$sender->sendMessage($this->prefix . "뽑기 목록: " . implode(", ", array_map(function(BoxMystery $box) : string{
							return $box->getName();
						}, $this->box)));
					break;
				case "정보":
					array_shift($args);
					$name = array_shift($args);
					if(!isset($name)){
						$sender->sendMessage($this->prefix . "뽑기 이름을 입력해주세요.");
						break;
					}
					if(!$this->getBoxByName($name) instanceof BoxMystery){
						$sender->sendMessage($this->prefix . "해당 이름의 뽑기가 존재하지 않습니다.");
						break;
					}
					$box = $this->getBoxByName($name);
					$sender->sendMessage($this->prefix . $box->getName() . " 의 뽑기 목록: " . implode(", ", array_map(function(array $array) : string{
							return $array["item"]->getName() . " 아이템(" . $array["per"] . "%)";
						}, $box->getAll())));
					break;
				default:
					$usages = [
						['/뽑기관리 뽑기생성 [이름] [열쇠이름]', '뽑기를 추가합니다.'],
						['/뽑기관리 뽑기제거 [이름]', '뽑기를 제거합니다.'],
						['/뽑기관리 아이템추가 [이름] [확률]', '뽑기에 아이템을 추가합니다. (숫자가 클 수록 잘뽑힘)'],
						['/뽑기관리 열쇠추가 [이름]', '뽑기에 필요한 열쇠를 추가합니다.'],
						['/뽑기관리 생성 [이름]', '뽑기를 설치합니다.'],
						['/뽑기관리 목록', '뽑기의 목록을 봅니다.'],
						['/뽑기관리 정보 [이름]', '뽑기의 정보를 봅니다.']
					];
					foreach($usages as $usage){
						$sender->sendMessage($this->prefix . $usage[0] . ' - ' . $usage[1]);
					}
			}
		}
		return true;
	}

	/**
	 * @param PlayerInteractEvent $event
	 *
	 * @handleCancelled true
	 */
	public function onInteract(PlayerInteractEvent $event){
		$player = $event->getPlayer();
		$block = $event->getBlock();
		if(isset($this->createQueue[$player->getName()])){
			if($this->getBoxByPosition($block->getPosition()) instanceof BoxMystery){
				$player->sendMessage($this->prefix . "해당 좌표에 이미 뽑기가 존재합니다.");
				return;
			}
			$this->pos[implode(":", [
				$block->getPosition()->getX(),
				$block->getPosition()->getY(),
				$block->getPosition()->getZ(),
				$block->getPosition()->getWorld()->getFolderName()
			])] = $this->createQueue[$player->getName()];
			$player->sendMessage($this->prefix . "뽑기를 생성했습니다.");
			unset($this->createQueue[$player->getName()]);
			return;
		}
		if(($box = $this->getBoxByPosition($block->getPosition())) instanceof BoxMystery){
			$key = $this->getKeyByName($box->getKey());
			if(!$player->getInventory()->contains($key)){
				$player->sendMessage($this->prefix . "뽑기에 필요한 아이템이 부족합니다.");
				return;
			}
			$player->getInventory()->removeItem($key);
			$rand = $box->getRandom();
			$player->getInventory()->addItem($rand);
			$player->sendMessage(sprintf($this->prefix . "%s 아이템이 뽑혔습니다.", $rand->getName()));
		}
	}

	/**
	 * @param BlockBreakEvent $event
	 *
	 * @priority HIGHEST
	 */
	public function onBreak(BlockBreakEvent $event){
		$block = $event->getBlock();
		$player = $event->getPlayer();
		if($this->getBoxByPosition($block->getPosition()) instanceof BoxMystery){
			if($player->hasPermission(DefaultPermissions::ROOT_OPERATOR)){
				unset($this->pos[implode(":", [
						$block->getPosition()->getX(),
						$block->getPosition()->getY(),
						$block->getPosition()->getZ(),
						$block->getPosition()->getWorld()->getFolderName()
					])]);
				$player->sendMessage($this->prefix . "뽑기 블럭을 제거했습니다.");
			}else{
				$event->cancel();
				$player->sendMessage($this->prefix . "뽑기 블럭은 부수실 수 없습니다.");
			}
		}
	}
}