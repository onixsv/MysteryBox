<?php
declare(strict_types=1);

namespace MysteryBox\box;

use pocketmine\item\Item;

class MysteryBox{

	protected array $items = [];

	protected string $name;

	protected string $key;

	public function __construct(string $name, array $data, string $key){
		$this->name = $name;
		$this->key = $key;
		foreach($data as $key => $datum){
			$this->items[$key] = [
				"per" => (int) $datum["per"],
				"item" => Item::jsonDeserialize($datum["item"])
			];
		}
	}

	public function getName() : string{
		return $this->name;
	}

	public function getKey() : string{
		return $this->key;
	}

	public function getRandom() : Item{
		/** @var Item[] $arr */
		$arr = [];
		foreach($this->items as $key => $datum){
			$per = (int) $datum["per"];
			for($i = 0; $i < $per; $i++){
				$arr[] = $datum["item"];
			}
		}
		$rand = $arr[array_rand($arr)];
		return $rand;
	}

	public function addItem(Item $item, int $per){
		$this->items[] = [
			"per" => $per,
			"item" => $item
		];
	}

	public function jsonSerialize() : array{
		$arr = [
			"name" => $this->name,
			"key" => $this->key,
			"item" => []
		];
		foreach($this->items as $key => $datum){
			$arr["item"] [] = [
				"per" => (int) $datum["per"],
				"item" => $datum["item"]->jsonSerialize()
			];
		}
		return $arr;
	}

	public function getAll() : array{
		return $this->items;
	}

	public static function jsonDeserialize(array $data) : MysteryBox{
		return new MysteryBox((string) $data["name"], (array) $data["item"], (string) $data["key"]);
	}
}