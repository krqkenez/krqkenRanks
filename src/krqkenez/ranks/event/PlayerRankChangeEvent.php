<?php

declare(strict_types=1);

namespace krqkenez\ranks\event;

use krqkenez\ranks\object\Rank;
use pocketmine\event\player\PlayerEvent;
use pocketmine\Player;

class PlayerRankChangeEvent extends PlayerEvent
{
	public function __construct(Player $player, private Rank $oldRank)
	{
		$this->player = $player;
	}

	public function getOldRank(): Rank
	{
		return $this->oldRank;
	}
}