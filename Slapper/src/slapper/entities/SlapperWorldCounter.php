<?php
namespace slapper\entities;

use pocketmine\entity\Human;
use pocketmine\network\protocol\AddPlayerPacket;
use pocketmine\network\protocol\RemoveEntityPacket;
use pocketmine\network\protocol\PlayerListPacket;
use pocketmine\level\particle\FloatingTextParticle;
use pocketmine\Player;

class SlapperWorldCounter extends Human {

	public $hasSpawned = [];

	/** @var FloatingTextParticle */
    public $ftp = null;
	
    public function addPlayerCountText(Player $player, string $text){
        if($this->ftp === null){
            $this->ftp = new FloatingTextParticle($this->subtract(0, 1), '', $text);
        }else{
            $this->ftp->setTitle($text);
        }
        $ftpPacket = $this->ftp->encode();
        if(is_array($ftpPacket)){
            foreach($ftpPacket as $ftpP){
                $player->dataPacket($ftpP);
            }
        }else{
            $player->dataPacket($ftpPacket);
        }
    }
	
    public function spawnTo(Player $player)
    {
        if ($player !== $this and !isset($this->hasSpawned[$player->getLoaderId()])) {
            $this->hasSpawned[$player->getLoaderId()] = $player;

            $uuid = $this->getUniqueId();
            $entityId = $this->getId();

            $pk = new AddPlayerPacket();
            $pk->uuid = $uuid;
            $pk->username = "";
            $pk->eid = $entityId;
            $pk->x = $this->x;
            $pk->y = $this->y;
            $pk->z = $this->z;
            $pk->yaw = $this->yaw;
            $pk->pitch = $this->pitch;
            $pk->item = $this->getInventory()->getItemInHand();
            $pk->metadata = [
                2 => [4, str_ireplace("{name}", $player->getName(), str_ireplace("{display_name}", $player->getDisplayName(), $player->hasPermission("slapper.seeId") ? $this->getDataProperty(2) . "\n" . \pocketmine\utils\TextFormat::GREEN . "Entity ID: " . $entityId : $this->getDataProperty(2)))],
                3 => [0, $this->getDataProperty(3)],
                15 => [0, 1],
		        23 => [7, -1],
		        24 => [0, 0]
            ];
            $player->dataPacket($pk);

            $this->inventory->sendArmorContents($player);

            $add = new PlayerListPacket();
            $add->type = 0;
            $add->entries[] = [$uuid, $entityId, isset($this->namedtag->MenuName) ? $this->namedtag["MenuName"] : "", $this->skinId, $this->skin];
            $player->dataPacket($add);
            if ($this->namedtag["MenuName"] === "") {
                $remove = new PlayerListPacket();
                $remove->type = 1;
                $remove->entries[] = [$uuid];
                $player->dataPacket($remove);
            }
        }
    }
	
	public function despawnFrom(Player $player){
        if(isset($this->hasSpawned[$player->getLoaderId()])){
            $pk = new RemoveEntityPacket();
            $pk->eid = $this->getId();
            $player->dataPacket($pk);
            unset($this->hasSpawned[$player->getLoaderId()]);
			
			if($this->ftp !== null){
				//TODO: Remove FloatingTextParticle removal hack
				$rp = new \ReflectionProperty(FloatingTextParticle::class, 'entityId');
				$rp->setAccessible(true);
				$pk1 = new RemoveEntityPacket();
				$pk1->eid = $rp->getValue($this->ftp);
				$player->dataPacket($pk1);
			}
        }
    }
}
