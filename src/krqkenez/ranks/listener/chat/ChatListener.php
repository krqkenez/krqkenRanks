<?php

declare(strict_types=1);

namespace krqkenez\ranks\listener\chat;

use krqkenez\ranks\event\PlayerRankChangeEvent;
use krqkenez\ranks\krqkenRanks;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

final class ChatListener implements Listener
{
    public function __construct(private krqkenRanks $plugin)
    {
    }

    /** @noinspection PhpUnused */
    public function handlePlayerJoin(PlayerJoinEvent $event): void
    {
        $this->plugin->updateNameTag($event->getPlayer());
    }

    /** @noinspection PhpUnused */
    public function handleRankChange(PlayerRankChangeEvent $event): void
    {
        $this->plugin->updateNameTag($event->getPlayer());
    }

    private function formatChat(Player $player, string $message): string
    {
        return str_replace(
            ["{NAME}", "{DISPLAY_NAME}", "{MESSAGE}"],
            [$player->getName(), $player->getDisplayName(), TextFormat::clean($message)],
            $this->plugin->getRank($player)->getChatFormat()
        );
    }

	/**
	 * @priority HIGHEST
	 * @noinspection PhpUnused
	 */
	public function handlePlayerChat(PlayerChatEvent $event): void
	{
		if ($event->isCancelled()) {
			return;
		}

		$player = $event->getPlayer();
		$message = trim($event->getMessage());
		$recipients = $event->getRecipients();
		$event->setCancelled(true);
			foreach ($recipients as $recipient) {
				$recipient->sendMessage($this->formatChat($player, $message));
		}
	}
}