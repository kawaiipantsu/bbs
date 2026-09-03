<?php

declare(strict_types=1);

namespace Bbs\Mud;

/**
 * Maps an item template to a specific icon key. The graphical client's
 * sprites.js draws a distinct glyph per key; anything unmapped falls back to a
 * type icon. Stored on mud_item_templates.icon at build time (mud_world.php)
 * and used at runtime by Api::itemIcon().
 */
final class Icons
{
    /** keyword (matched in "name keywords") => icon key, first hit wins */
    private const RULES = [
        // --- signature / unambiguous first ---
        'mantis'      => 'mantis',
        'bone saw'    => 'saw', 'bonesaw' => 'saw',
        'electro-katana' => 'katana', 'electrokatana' => 'katana', 'monokatana' => 'katana',
        // --- weapons: ranged ---
        'zip'         => 'zipgun',
        'revolver'    => 'revolver', 'magnum' => 'revolver', 'wheelgun' => 'revolver',
        'machine pistol' => 'smg', 'smg' => 'smg', 'submachine' => 'smg', 'twin' => 'smg',
        'carbine'     => 'rifle', 'antimateriel' => 'sniper', 'sniper' => 'sniper', 'precision' => 'sniper',
        'hunting rifle' => 'rifle', 'rifle' => 'rifle',
        'shotgun'     => 'shotgun', 'crusher' => 'shotgun', 'boomstick' => 'shotgun',
        'railgun'     => 'techpistol', 'tech pistol' => 'techpistol',
        'flare'       => 'flare',
        'pistol'      => 'pistol', 'handgun' => 'pistol',
        // --- weapons: melee ---
        'monowire'    => 'whip', 'whip' => 'whip',
        'electrokatana' => 'katana', 'monokatana' => 'katana', 'katana' => 'katana', 'tanto' => 'knife', 'sword' => 'sword',
        'nail bat'    => 'bat', 'nailbat' => 'bat', 'baseball' => 'bat', 'bat' => 'bat',
        'sledge'      => 'sledge', 'maul' => 'sledge',
        'fireaxe'     => 'axe', 'fire axe' => 'axe', 'axe' => 'axe',
        'halligan'    => 'crowbar', 'tyre iron' => 'crowbar', 'tire iron' => 'crowbar', 'pry' => 'crowbar', 'rebar' => 'crowbar',
        'wrench'      => 'wrench',
        'stun baton'  => 'baton', 'riot' => 'baton', 'baton' => 'baton',
        'machete'     => 'machete',
        'cleaver'     => 'cleaver',
        'bonesaw'     => 'saw', 'bone saw' => 'saw',
        'plasma cutter' => 'cutter', 'cutter' => 'cutter',
        'knuckles'    => 'fist', 'duster' => 'fist',
        'switchblade' => 'knife', 'combat knife' => 'knife', 'knife' => 'knife', 'blade' => 'knife',
        'mantis'      => 'mantis',
        // --- cyberware (check before generic armor/gear) ---
        'mantis blade' => 'mantis',
        'neural jack' => 'jack', 'jack implant' => 'jack',
        'kiroshi'     => 'optic', 'curator\'s lens' => 'lens', 'lens' => 'optic', 'optic' => 'optic', 'occipital' => 'chip',
        'gorilla'     => 'gorillarm', 'strength servo' => 'gorillarm',
        'reflex booster' => 'chip', 'reflex chip' => 'chip', 'firewall' => 'chip', 'smartlink' => 'chip', 'smart-link' => 'chip',
        'coprocessor' => 'chip', 'memory' => 'chip', 'combat cortex' => 'chip', 'pain editor' => 'chip', 'occipital chip' => 'chip',
        'second heart' => 'heart', 'heart' => 'heart',
        'adrenal'     => 'pump', 'pump' => 'pump', 'shunt' => 'pump',
        'booster spine' => 'spine', 'spine' => 'spine',
        'cooling mesh' => 'weave', 'camo' => 'weave', 'subdermal' => 'weave', 'dermal' => 'weave', 'gill' => 'weave', 'ablative' => 'weave',
        'launch system' => 'launcher', 'pls' => 'launcher',
        'grip'        => 'gorillarm',
        // --- armour / clothing ---
        'poncho'      => 'poncho', 'longcoat' => 'longcoat', 'duster' => 'longcoat',
        'leathers'    => 'jacket', 'house jacket' => 'jacket', 'road leathers' => 'jacket', 'jacket' => 'jacket',
        'trench'      => 'longcoat', 'coat' => 'coat',
        'suit'        => 'suit', 'business' => 'suit', 'tailored' => 'suit',
        'maxtac helm' => 'helmet', 'ballistic helmet' => 'helmet', 'helm' => 'helmet', 'helmet' => 'helmet',
        'beanie'      => 'hat', 'cap' => 'hat',
        'gas mask'    => 'gasmask', 'respirator' => 'gasmask',
        'scarf'       => 'scarf',
        'goggles'     => 'goggles', 'mirrorshade' => 'shades', 'sunglasses' => 'shades', 'shades' => 'shades', 'glasses' => 'shades',
        'gloves'      => 'gloves',
        'exo-boots'   => 'boots', 'work boots' => 'boots', 'steel-toe' => 'boots', 'steeltoe' => 'boots', 'boots' => 'boots',
        'cargo pants' => 'pants', 'trousers' => 'pants', 'pants' => 'pants',
        'shield'      => 'shield',
        'kevlar'      => 'vest', 'bulletproof' => 'vest', 'security vest' => 'vest', 'vest' => 'vest',
        'guard plate' => 'plate', 'arasaka plate' => 'plate', 'plate' => 'plate',
        'ammo rig'    => 'bandolier', 'bandolier' => 'bandolier',
        'trophy chain' => 'chain', 'necklace' => 'chain', 'chain' => 'chain',
        'wire crown'  => 'crown', 'crown' => 'crown',
        'monocle'     => 'monocle',
        'utility belt' => 'belt', 'belt' => 'belt',
        'tool harness' => 'harness', 'harness' => 'harness',
        'interception rig' => 'scanner', 'comms rig' => 'scanner',
        'signal scrambler' => 'jammer', 'scrambler' => 'jammer',
        // --- computers ---
        'deck'        => 'deck', 'cyberdeck' => 'deck',
        'burner phone' => 'phone', 'phone' => 'phone',
        // --- food ---
        'ramen'       => 'ramen', 'noodles' => 'ramen',
        'yakitori'    => 'skewer', 'skewer' => 'skewer',
        'rice'        => 'rice',
        'bento'       => 'bento',
        'burrito'     => 'burrito', 'wrap' => 'burrito',
        'bun'         => 'bun', 'bread' => 'bun',
        'ration'      => 'rationbar', 'protein' => 'rationbar', 'mre' => 'rationbar',
        'crisps'      => 'crisps', 'chips' => 'crisps', 'snack' => 'crisps',
        'jerky'       => 'jerky',
        'stew'        => 'can', 'tin' => 'can',
        'honey'       => 'jar',
        // --- drink ---
        'nicola'      => 'can', 'cola' => 'can', 'soda' => 'can', 'broseph' => 'energydrink', 'energy drink' => 'energydrink',
        'sake bottle' => 'bottle', ' sake' => 'bottle',
        'whiskey'     => 'flask', 'booze' => 'flask', 'flask' => 'flask',
        'coffee'      => 'coffee', 'thermos' => 'coffee',
        'smoothie'    => 'smoothie',
        'water'       => 'waterbulb', 'electrolyte' => 'waterbulb', 'gel' => 'waterbulb',
        // --- drugs / meds ---
        'first-aid'   => 'medkit', 'firstaid' => 'medkit', 'aid kit' => 'medkit',
        'inhaler'     => 'inhaler', 'maxdoc' => 'inhaler',
        'bounceback'  => 'syringe', 'bonesetter' => 'syringe', 'stim' => 'syringe', 'shot' => 'syringe',
        'berserk'     => 'drugvial', 'reflex'  => 'drugvial', 'focus' => 'drugvial', 'ironhide' => 'drugvial',
        'sandevistan' => 'drugvial', 'sande' => 'drugvial', 'booster' => 'drugvial', 'nootropic' => 'drugvial',
        'heat-sink flush' => 'cartridge', 'heat-purge' => 'cartridge', 'coolant' => 'cartridge', 'cartridge' => 'cartridge',
        // --- gadgets ---
        'lockpick'    => 'lockpick', 'lock-decryptor' => 'decryptor', 'decryptor' => 'decryptor',
        'cloner'      => 'cloner', 'rfid' => 'cloner',
        'grapple'     => 'grapple',
        'smoke grenade' => 'grenade', 'emp' => 'grenade', 'breaching charge' => 'grenade', 'breaching' => 'grenade', 'grenade' => 'grenade', 'demo charge' => 'grenade',
        'camera spike' => 'spike', 'spike' => 'spike',
        'drone jammer' => 'jammer', 'antidrone' => 'jammer', 'jammer' => 'jammer',
        'med-scanner' => 'scanner', 'scanner' => 'scanner',
        'bolt cutter' => 'boltcutter', 'boltcutter' => 'boltcutter',
        'ice breaker' => 'icebreaker', 'icebreaker' => 'icebreaker', 'shard of black ice' => 'icebreaker', 'black ice' => 'icebreaker',
        'toolkit'     => 'toolkit', 'ripper' => 'toolkit', 'ripperkit' => 'toolkit', 'extraction' => 'toolkit',
        'tracer'      => 'tracer',
        // --- light ---
        'flashlight'  => 'flashlight', 'torch' => 'flashlight', 'wind-up' => 'flashlight',
        'chemlight'   => 'glowstick', 'glowstick' => 'glowstick',
        'headlamp'    => 'headlamp',
        'lantern'     => 'lantern', 'uv' => 'lantern',
        'spotlight'   => 'headlamp', 'helmet spotlight' => 'headlamp',
        // --- containers ---
        'duffel'      => 'bag', 'kitbag' => 'bag', 'kit bag' => 'bag', 'holdall' => 'bag', 'backpack' => 'bag', 'pack' => 'bag',
        'document case' => 'briefcase', 'briefcase' => 'briefcase',
        'lockbox'     => 'lockbox', 'strongbox' => 'lockbox',
        // --- lore / quest / junk ---
        'index'       => 'book', 'book' => 'book', 'ledger' => 'book',
        'conelrad key' => 'key', 'keyfragment' => 'key', 'key-fragment' => 'key', 'key' => 'key',
        'procurement file' => 'file', 'file' => 'file', 'folder' => 'file',
        'nomad map'   => 'map', 'chart' => 'map', 'map' => 'map',
        'reel-to-reel' => 'tape', 'tape' => 'tape',
        'braindance'  => 'disc', 'bd disc' => 'disc', 'disc' => 'disc',
        'graffiti marker' => 'marker', 'marker' => 'marker',
        'locket'      => 'locket',
        'trophy string' => 'trophystring', 'ears' => 'trophystring',
        'arcade tickets' => 'tickets', 'tickets' => 'tickets',
        'access badge' => 'keycard', 'keycard' => 'keycard', 'access card' => 'keycard',
        'pigeon'      => 'pigeon', 'bird' => 'pigeon',
        'circuit board' => 'circuit', 'circuit' => 'circuit',
        'stolen fibre' => 'fibre', 'fibre' => 'fibre',
        'drone servo' => 'servo', 'servo' => 'servo', 'actuator' => 'servo',
        'solar cell'  => 'solarcell', 'solarcell' => 'solarcell', 'panel' => 'solarcell',
        'copper cable' => 'cable', 'cable' => 'cable', 'coil' => 'cable',
        'cyber-scrap' => 'scrap', 'cyberscrap' => 'scrap', 'chrome' => 'scrap', 'scrap' => 'scrap',
        'rat tail'    => 'tail', 'tail' => 'tail',
        'hostile code' => 'datafrag', 'fragment of' => 'datafrag', 'data ghost' => 'datafrag',
        'credchip'    => 'credchip', 'cred chip' => 'credchip',
        'pre-war cash' => 'cashbundle', 'prewar' => 'cashbundle', 'scrip' => 'cashbundle', 'bundle of scrip' => 'cashbundle', 'cash' => 'cashbundle',
        'document tube' => 'tube', 'courier tube' => 'tube', 'tube' => 'tube',
        'body bag'    => 'bodybag',
        'data shard'  => 'shard', 'datashard' => 'shard', 'shard' => 'shard',
        'jar'         => 'jar',
    ];

    /** type => generic fallback icon key */
    private const BY_TYPE = [
        'weapon' => 'knife', 'armor' => 'jacket', 'implant' => 'chip', 'computer' => 'deck',
        'food' => 'rationbar', 'drink' => 'can', 'drug' => 'syringe', 'gadget' => 'toolkit',
        'light' => 'flashlight', 'container' => 'bag', 'currency' => 'eddies', 'material' => 'scrap',
        'lore' => 'shard', 'junk' => 'junk', 'gear' => 'harness',
    ];

    public static function forItem(string $name, string $keywords, string $type, string $slot = '', string $flags = ''): string
    {
        $hay = strtolower($name . ' ' . $keywords);
        // ranged weapons get a 'gun-ish' default even if no keyword hits
        if (str_contains((string) $flags, 'ranged')) {
            foreach (self::RULES as $needle => $icon) {
                if (str_contains($hay, $needle) && in_array($icon, ['pistol', 'revolver', 'smg', 'rifle', 'shotgun', 'sniper', 'techpistol', 'flare', 'zipgun'], true)) {
                    return $icon;
                }
            }
            return 'pistol';
        }
        foreach (self::RULES as $needle => $icon) {
            if (str_contains($hay, $needle)) {
                // implant slot overrides a melee-ish keyword hit
                if (str_starts_with($slot, 'implant_') && !in_array($icon, ['chip', 'optic', 'lens', 'jack', 'heart', 'pump', 'spine', 'weave', 'gorillarm', 'mantis', 'launcher'], true)) {
                    continue;
                }
                return $icon;
            }
        }
        return self::BY_TYPE[$type] ?? 'junk';
    }
}
