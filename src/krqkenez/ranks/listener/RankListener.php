<?php

declare(strict_types=1);

namespace krqkenez\ranks\listener;

use krqkenez\ranks\event\PlayerRankChangeEvent;
use krqkenez\ranks\krqkenRanks;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;

final class RankListener implements Listener
{
	public function __construct(private krqkenRanks $plugin)
	{
	}

	/**
	 * @priority LOWEST
	 * @noinspection PhpUnused
	 */
	public function handlePlayerJoin(PlayerJoinEvent $event): void
	{
		$player = $event->getPlayer();
		$rank = $this->plugin->getRankFromDatabase($player->getName());

		if ($this->plugin->doesRankExist($rank)) {
			$this->plugin->addRank($player, $rank);
		} else {
			$this->plugin->setRank($player, $this->plugin->getDefaultRank());
		}

		$this->plugin->setAttachment($player, $player->addAttachment($this->plugin));
		$this->plugin->updatePermissions($player);
	}

	/**
	 * @priority LOWEST
	 * @noinspection PhpUnused
	 */
	public function handlePlayerRankChange(PlayerRankChangeEvent $event): void
	{
		$this->plugin->updatePermissions($event->getPlayer());
	}

	/**
	 * @priority HIGHEST
	 * @noinspection PhpUnused
	 */
	public function handlePlayerQuit(PlayerQuitEvent $event): void
	{
		$player = $event->getPlayer();
		$this->plugin->removeRank($player);
		$this->plugin->removeAttachment($player);
	}
}