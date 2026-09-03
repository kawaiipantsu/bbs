<?php

/**
 * Hackers-MUD content expansion #1 - loaded by mysql/mud_world.php.
 *
 * In scope from the caller: the $room / $mob / $item closures and the
 * $SHOP, $QUEST, $EX arrays, plus $EXTRAS (room lore) and $SPAWN_EXT
 * (extra mob placements) to fill.
 *
 * vnum ranges used here:
 *   rooms   1600-1799
 *   items   melee 1013+ · ranged 2007+ · armour 3022+ · gear/implant 4031+ /
 *           2231+ · decks 5006+ · consumable 6041+ · gadget 6529+ ·
 *           legendary 6601+ · light 6703+ · container 6802+ · lore/junk 6917+
 *   mobs    5074-5199
 *   quests  7061-7199
 */

/* =====================================================================
 *  ROOMS
 * ===================================================================== */

/* ---- Kabuki rooftops & the rooftop net-run route (z = 2) ---------- */
$room(1600, 'kabuki', 'Kabuki Rooftops', 1, 0, 2,
    "Tar paper, aerials and a forest of illegal satellite dishes. You can cross half of Kabuki up here without touching the street, if your nerve holds and the gaps stay jumpable.", '');
$room(1601, 'kabuki', 'The Gap', 2, 0, 2,
    "Two buildings lean toward each other over a four-storey drop. Someone laid a scaffold plank across. Someone else took it. There is a grab-rail on the far side and a lot of air in between.", '');
$room(1602, 'kabuki', "Wirehead's Nest", 3, 0, 2,
    "A shipping container hauled onto a roof and packed with salvaged servers, humming on stolen power. A fixer who calls herself Wirehead runs rooftop work from a gaming chair with a cracked armrest.", 'safe indoors');
$room(1603, 'kabuki', 'Antenna Cluster', 3, 1, 2,
    "A thicket of cell masts and microwave horns. The RF up here makes your fillings sing and your deck run hot. Good uplink, though - the best in the district.", 'net');
$room(1604, 'kabuki', 'The Pigeon Coop', 4, 0, 2,
    "An old man's rooftop pigeon loft, still tended, birds still coming home. He is not here. His folding chair is still warm. There is a shotgun leaning inside the coop door.", '');
$room(1605, 'kabuki', 'Rooftop Garden', 2, 1, 2,
    "Someone hauled up soil and grows real vegetables between the ventilation stacks - tomatoes, chillies, a lemon tree in a barrel. A hand-lettered sign: TAKE WHAT YOU NEED. LEAVE THE REST. PEOPLE ARE WATCHING.", 'safe');
$room(1606, 'kabuki', 'Neon Kitsune - Roof Access', 4, 1, 2,
    "A steel door with the Neon Kitsune's fox-mask logo etched into it, propped open with a fire extinguisher. Bass comes up the stairwell like a heartbeat. A bouncer's stool sits empty.", '');
$room(1607, 'kabuki', 'Comms Mast Platform', 3, 2, 2,
    "A caged ladder up the district's tallest mast. From the platform the whole city unrolls - Corpo Plaza's towers, the Combat Zone's dead glow, the sick shimmer of the Blackwall past everything. A jack point is bolted to the rail.", 'net');

/* ---- Kabuki Hab-Stack: the common deck new runners actually land on --- */
$room(1022, 'kabuki', 'Hab-Stack Common Deck', 1, 0, 0,
    "The shared floor at the foot of the pod-stacks. A row of vending machines glowing every colour of additive, a coffee unit fused to its own ring-stains, four couches that do not match and one that is mostly tape. A laundry drum thumps somewhere behind a partition. By the dead lift someone has bolted up a job-board thick with overlapping printouts and taped a hand-lettered sign above it: NEW? READ THIS BEFORE YOU GET KILLED.\nThe stairwell is WEST; a short hop SOUTH is the all-night coin laundry.", 'safe indoors shop board');
$room(1023, 'kabuki', 'Kabuki Coin Laundry', 2, 0, 0,
    "Twelve machines, nine of them working, open all night because the pods have no plumbing. Warm, loud, smelling of hot lint and cheap detergent. People sit out bad nights in here, where it is bright and nobody starts anything. A dented wall dispenser sells the essentials at a mark-up nobody bothers to argue with.", 'safe indoors shop');

/* ---- The Neon Kitsune (arcade zone) ------------------------------- */
$room(1610, 'arcade', 'Neon Kitsune - Main Floor', 0, 0, 0,
    "Three floors of noise wrapped around an atrium. Braindance pods glow like aquariums, arcade cabinets scream for attention, and everywhere the fox-mask logo watches. Tyger Claws in gold jackets lean on every rail.", 'safe indoors');
$room(1611, 'arcade', 'BD Booth Row', -1, 0, 0,
    "Private braindance booths behind bead curtains, each humming with someone else's afternoon. A tech in a Kitsune polo shirt swaps wetware discs from a locked cart.", 'indoors');
$room(1612, 'arcade', 'The Arcade', 1, 0, 0,
    "Cabinets wall to wall: light-gun games, a full-motion mech sim, a dance machine nobody has beaten. Tickets spool onto the sticky floor. The prize counter sells things you cannot win.", 'indoors');
$room(1613, 'arcade', 'The Back Room', -2, 0, 0,
    "Past a door marked STAFF: unlicensed braindances stacked to the ceiling. Snuff, torture, worse, sorted by a bored kid with a label gun. The Claws' real money is in this room.", 'indoors');
$room(1614, 'arcade', 'VIP Lounge', 0, 1, 1,
    "Up a mirrored stair: low couches, a private bar, a window onto the floor below. This is where the Claws take people who matter, or people who owe.", 'indoors');
$room(1615, 'arcade', "The Kitsune's Office", 0, 2, 1,
    "A tatami room incongruous in all this neon. A low table, a sword stand, a wall of monitors showing every camera in the building. The manager kneels at the table, unhurried, waiting for you to explain yourself.", 'indoors');
$room(1616, 'arcade', 'Kitsune - Loading Dock', 2, 1, 0,
    "Where the discs come in and the problems go out. A van with tinted windows idles. Two Claws are loading something person-shaped and quiet into the back.", '');
$room(1617, 'arcade', 'Kitsune - Fire Stairs', 1, 1, 0,
    "Concrete fire stairs, the alarm bar chained shut against runners. Graffiti layered so deep it has texture. It comes out in Ping Alley if you can get the chain off.", '');

/* ---- Militech office tower - 40th floor (corpo, z = 3) ------------ */
$room(1650, 'corpo', 'Militech Tower - 40th Floor Lobby', -1, 0, 3,
    "The elevator opens on hush and grey. A reception desk shaped like a gun sight, a wall of balanced-scale awards, and a security turnstile that reads your chrome and does not like what it finds.", 'indoors');
$room(1651, 'corpo', 'Cubicle Farm', -1, 1, 3,
    "An acre of identical desks under lighting engineered to prevent both headaches and joy. Most are empty - it is late, or there is a war on somewhere, or both. Screens left logged in.", 'indoors');
$room(1652, 'corpo', 'Server Closet - 40th', -2, 1, 3,
    "A walk-in cabinet of blinking Militech iron, cold enough to see your breath. One rack has been left open, a maintenance laptop still plugged in and unlocked. Careless. Or bait.", 'indoors nomob');
$room(1653, 'corpo', 'Break Room - 40th', 0, 1, 3,
    "A kitchenette with a passive-aggressive note on the fridge and a coffee machine worth more than a car. A vending machine hums in the corner, fully stocked, corporate-subsidised.", 'indoors');
$room(1654, 'corpo', 'Corner Office - 40th', -1, 2, 3,
    "Floor-to-ceiling glass, a desk you could land a drone on, a scale model of a rail gun under a dome. The VP of Procurement is working late, and the slab of muscle by the window is her problem-solver.", 'indoors');
$room(1655, 'corpo', 'Executive Washroom', -2, 2, 3,
    "Marble, brass, a hand towel service. Behind the mirror over the third basin: a shallow safe someone uses to skim, currently holding more than a washroom safe should.", 'indoors nomob');

/* ---- The Badlands Edge ------------------------------------------- */
$room(1700, 'badlands', 'City Limits Gate', 0, 0, 0,
    "The last checkpoint. A boom barrier, a sun-bleached CITY OF NIGHT sign full of bullet holes, and beyond it the highway running straight into a heat-shimmer horizon. The air out here actually moves.", '');
$room(1701, 'badlands', 'The Old Highway', 0, 1, 0,
    "Six lanes of cracked blacktop, weeds in the median, the skeletons of cars pushed to the shoulder decades ago. Wind and grit and the tick of cooling metal.", '');
$room(1702, 'badlands', 'Highway - Overturned Rig', 1, 1, 0,
    "A jackknifed road train, trailer split open and long since stripped. Someone lives in the cab now - washing on a line, a solar panel, a dog that does not bark, just watches.", '');
$room(1703, 'badlands', 'Aldecaldo Camp', 0, 2, 0,
    "Vehicles drawn into a rough circle, awnings between them, a fire pit, kids and dogs and the smell of real cooking. The Aldecaldos made a home out here that the city never managed inside its walls.", 'safe');
$room(1704, 'badlands', 'Aldecaldo Camp - Elder\'s Rig', -1, 2, 0,
    "The oldest vehicle in the circle, a rolling workshop and archive. The clan elder sits in the doorway mending a solar cell, and knows more about who runs Night City than anyone with an office.", 'safe indoors');
$room(1705, 'badlands', 'The Motorpool', 1, 2, 0,
    "Engine blocks on stands, a pit dug into the dirt, tools organised with religious care. The clan's mechanics will teach anyone willing to get their hands dirty - reflexes, nerve, the body's own machinery.", 'safe');
$room(1706, 'badlands', 'Solar Farm Ruins', -1, 1, 0,
    "Rank after rank of cracked photovoltaic panels, most dead, a few still tracking the sun out of habit. Cabling worth a fortune, if you can carry it and the things nesting in the inverters let you.", '');
$room(1707, 'badlands', 'Radio Tower', -1, 0, 0,
    "A lattice mast a hundred metres up, guy-wired to concrete blocks, still broadcasting a pirate station nobody admits to running. The equipment shack at the base is unlocked and full of salvage.", 'net');
$room(1708, 'badlands', 'The Dust Bowl', 1, 0, 0,
    "A dry lake bed the size of a district, used for races nobody sanctions and dumps nobody investigates. Tyre tracks, scorch marks, and a low rise on the far side where something is parked and watching.", '');
$room(1709, 'badlands', 'Raffen Shiv Camp', 2, 0, 0,
    "A knot of stripped vehicles and razor wire around a bonfire of tyres. The Raffen Shiv are nomads the other clans exiled - too cruel, too hungry. They keep their trophies where visitors can see them.", '');
$room(1710, 'badlands', 'Buried Gas Station', 0, -1, 0,
    "A pre-collapse service station half-swallowed by a dune, the price sign still lit somehow. The tanks below are dry but the cellar is a cool, dark stash and someone has been using it.", 'dark');

/* ---- Deep Undercity --------------------------------------------- */
$room(1750, 'undercity', 'The Deep Junction', 0, 7, -1,
    "Below even the storm drains: a brick chamber where six older tunnels meet, air pressure changing when you move. The bricks are hand-laid and older than anyone's records of this place.", 'dark');
$room(1751, 'undercity', 'Fungal Gallery', -1, 7, -1,
    "The walls are soft with pale growth that gives its own dim green light - enough to see by, if you can stand the smell and the way it flinches from your footsteps.", 'dark');
$room(1752, 'undercity', 'The Cathedral of Pipes', 0, 8, -1,
    "A vaulted space where a hundred trunk mains cross, dripping, singing, vast. Someone has strung a hammock between two of them and left a shrine of batteries and bottle caps.", 'dark');
$room(1753, 'undercity', 'Bone Pit', 1, 8, -1,
    "A sump the diggers used as an ossuary when the cemeteries filled. The water is low. What is stacked against the walls is not driftwood.", 'dark');
$room(1754, 'undercity', 'Lost Line Terminus', 0, 9, -1,
    "The far end of the cancelled metro, where the tunnel just stops at a wall of poured concrete. A single railcar sits on the buffers, doors open, seats intact, waiting eighty years for a service that never came.", 'dark');
$room(1755, 'undercity', 'The Still Pool', -1, 9, -1,
    "A perfectly circular pool of black water in a perfectly circular room. No inflow, no outflow, no ripple. Drop a coin and you never hear it land.", 'dark');
$room(1756, 'undercity', 'Nest of the Blind', 1, 9, -1,
    "A warm chamber lined with shredded plastic and hair. The things that live here lost their eyes generations ago and do not miss them. They are already facing the tunnel you came in by.", 'dark');
$room(1757, 'undercity', 'Bunker Door', 0, 10, -1,
    "A Cold-War blast hatch set in living rock, wheel-locked, a faded trefoil and the word CONELRAD stencilled above. The wheel turns, grudgingly. Someone oiled it. Recently.", 'dark');
$room(1758, 'undercity', 'Pre-War Bunker', 0, 11, -1,
    "Inside: bunks, a decade of ration tins, a map of a country that redrew itself, and a reel-to-reel that clicks on when you enter and plays a countdown that never finishes. Dry. Defensible. Not empty.", 'dark indoors');

/* =====================================================================
 *  EXITS  (appended to $EX; auto-reversed by the builder)
 * ===================================================================== */

$EX = array_merge($EX, [
    // Kabuki Hab-Stack: common deck + coin laundry, off the stairwell
    [1001, 'e', 1022], [1022, 's', 1023],
    // Kabuki rooftops - climb up from the street-level rooftop (1018) and Ping Alley
    [1018, 'e', 1600, ['descr' => 'A run of planks and ductwork leads east across the roofscape.']],
    [1600, 'e', 1601], [1601, 'e', 1602, ['keyword' => 'container', 'descr' => "Wirehead's container door stands open, screenlight spilling out."]],
    [1602, 'n', 1603], [1600, 'n', 1605], [1605, 'e', 1601], [1601, 'e', 1604],
    [1604, 'n', 1606], [1603, 'n', 1607],
    [1606, 'd', 1610, ['descr' => 'The stairwell drops into the noise and neon of the Kitsune.']],
    [1019, 'u', 1600, ['keyword' => 'ladder', 'descr' => 'A maintenance ladder bolted to the wall of Ping Alley climbs to the roofs.']],
    // Neon Kitsune internal
    [1610, 'w', 1611], [1610, 'e', 1612], [1611, 'w', 1613, ['keyword' => 'door', 'locked' => 1, 'hack_dc' => 10, 'descr' => 'A STAFF door with a card reader. It is not for customers.']],
    [1610, 'u', 1614], [1614, 'n', 1615], [1610, 'se', 1616], [1610, 's', 1617],
    [1617, 's', 1019, ['keyword' => 'chain', 'locked' => 1, 'hack_dc' => 4, 'descr' => 'A chained fire door. A bolt-cutter or a good kick, and it opens on Ping Alley.']],
    [1616, 'e', 1002, ['oneway' => true, 'descr' => 'The dock gate lets out onto Jig-Jig Street.']],
    // Militech tower floor - off the Arasaka mezzanine's shared elevator core... use the corpo maintenance spine
    [1210, 'u', 1650, ['keyword' => 'elevator', 'descr' => 'A service elevator rises to the Militech floors.']],
    [1650, 'n', 1651], [1651, 'w', 1652], [1651, 'e', 1653], [1651, 'n', 1654],
    [1654, 'w', 1655], [1653, 'e', 1650],
    // Badlands - out through the Corpo checkpoint's far side / the plaza transit
    [1201, 'w', 1700, ['keyword' => 'maglev', 'descr' => 'The maglev runs a single line out past the wall to the City Limits.']],
    [1700, 'n', 1701], [1701, 'n', 1703], [1701, 'e', 1702], [1701, 'w', 1706],
    [1703, 'w', 1704], [1703, 'e', 1705], [1706, 'w', 1707], [1701, 's', 1700],
    [1703, 'n', 1708], [1708, 'e', 1709], [1708, 's', 1710],
    [1707, 'd', 1500, ['keyword' => 'conduit', 'hidden' => 1, 'descr' => 'A buried service line runs from the tower base all the way to the Fringe datacentres.']],
    // Deep Undercity - down from the Cistern
    [1410, 'd', 1750, ['keyword' => 'shaft', 'descr' => 'A brick shaft with iron rungs drops into older dark.']],
    [1750, 'w', 1751], [1750, 'n', 1752], [1752, 'e', 1753], [1752, 'n', 1754],
    [1754, 'w', 1755], [1754, 'e', 1756], [1752, 'nw', 1757],
    [1757, 'n', 1758, ['keyword' => 'hatch', 'locked' => 1, 'hack_dc' => 8, 'descr' => 'The blast hatch wheel. Someone keeps it oiled.']],
]);

/* =====================================================================
 *  ITEMS
 * ===================================================================== */

/* ---- melee 1013+ ------------------------------------------------- */
$item(1013, 'a nail bat', 'nailbat bat nail', 'weapon', 'wield', 1.8, 26, ['dmg' => '1d8+1', 'long_desc' => 'A bat with a considered arrangement of roofing nails through the fat end. Kabuki craftsmanship.', 'flags' => 'melee']);
$item(1014, 'a tyre iron', 'tyre iron tire', 'weapon', 'wield', 1.6, 16, ['dmg' => '1d6+1', 'long_desc' => 'Bent for leverage, good for lug nuts and skulls alike. The Badlands standard.', 'flags' => 'melee']);
$item(1015, 'brass knuckles', 'knuckles brass duster', 'weapon', 'wield', 0.3, 35, ['dmg' => '1d4+2', 'long_desc' => 'Turns a punch into a statement. Fits in a pocket, which is the point.', 'flags' => 'melee']);
$item(1016, 'a combat knife', 'combat knife blade', 'weapon', 'wield', 0.5, 70, ['dmg' => '1d6+1', 'level_req' => 2, 'long_desc' => 'Military pattern, blood groove, glass-breaker pommel. Militech makes a lot of these and loses track of most.', 'flags' => 'melee', 'stat_mods' => ['reflex' => 1]]);
$item(1017, 'a fireman\'s halligan', 'halligan bar pry', 'weapon', 'wield', 3.5, 60, ['dmg' => '2d4+1', 'level_req' => 3, 'long_desc' => 'Adze, pick and fork on a metre of forged steel. Opens doors, cars and arguments.', 'flags' => 'melee', 'stat_mods' => ['body' => 1]]);
$item(1018, 'a monokatana', 'monokatana katana blade', 'weapon', 'wield', 1.3, 980, ['dmg' => '2d8+2', 'level_req' => 7, 'long_desc' => 'A production blade with a monomolecular edge - none of the Tyger Claw soul, all of the cut.', 'flags' => 'melee illegal', 'stat_mods' => ['reflex' => 2]]);
$item(1019, 'a mantis blade', 'mantis blade arm implant', 'weapon', 'wield', 0.0, 1600, ['dmg' => '3d6+2', 'level_req' => 8, 'long_desc' => 'A folding blade housed in the forearm. It is never not on you, which is a feeling that takes getting used to.', 'flags' => 'melee illegal', 'stat_mods' => ['reflex' => 2, 'body' => 1]]);
$item(1020, 'a riot shield baton', 'baton riot shield', 'weapon', 'wield', 1.4, 150, ['dmg' => '1d8+2', 'level_req' => 4, 'long_desc' => 'NCPD crowd-control issue. Shocks on contact and browns out cheap chrome. Illegal for you to hold and they know it.', 'flags' => 'melee illegal']);
$item(1021, 'the Kitsune\'s tanto', 'tanto knife kitsune', 'weapon', 'wield', 0.6, 1200, ['dmg' => '2d6+3', 'level_req' => 7, 'long_desc' => 'The Kitsune manager\'s side knife, lacquer scabbard, fox-mask menuki. Taken, it makes every Tyger Claw in the room reconsider.', 'flags' => 'melee illegal', 'stat_mods' => ['reflex' => 2, 'cool' => 1]]);

/* ---- ranged 2007+ --------------------------------------------- */
$item(2007, 'a nomad hunting rifle', 'rifle hunting nomad bolt', 'weapon', 'wield', 3.8, 320, ['dmg' => '2d8+1', 'level_req' => 4, 'long_desc' => 'Bolt-action, wood stock worn smooth, a scope held on with hose clamps. It has fed a family for years.', 'flags' => 'ranged']);
$item(2008, 'a machine pistol', 'machine pistol smg', 'weapon', 'wield', 1.1, 300, ['dmg' => '2d6', 'level_req' => 3, 'long_desc' => 'Cyclic rate you can feel in your teeth. Empties a magazine before you have finished deciding to.', 'flags' => 'ranged illegal', 'stat_mods' => ['reflex' => 1]]);
$item(2009, 'a Militech "Crusher" shotgun', 'shotgun crusher militech', 'weapon', 'wield', 3.6, 620, ['dmg' => '3d6+3', 'level_req' => 5, 'long_desc' => 'Corporate pattern, breaching choke, a stock that folds. At range, a suggestion. Up close, a verdict.', 'flags' => 'ranged', 'stat_mods' => ['body' => 1]]);
$item(2010, 'a tech pistol', 'tech pistol railgun', 'weapon', 'wield', 1.2, 700, ['dmg' => '2d10', 'level_req' => 6, 'long_desc' => 'Charges a slug on a rail and puts it through cover and whoever was using it for cover.', 'flags' => 'ranged illegal', 'stat_mods' => ['tech' => 1, 'reflex' => 1]]);
$item(2011, 'a smart SMG', 'smart smg submachine', 'weapon', 'wield', 2.0, 1100, ['dmg' => '3d6+2', 'level_req' => 8, 'long_desc' => 'Guided rounds that chase a tagged target around corners. Needs a smart-link to be worth the money.', 'flags' => 'ranged illegal', 'stat_mods' => ['reflex' => 2]]);
$item(2012, 'an antimateriel rifle', 'antimateriel rifle heavy sniper', 'weapon', 'wield', 8.0, 2400, ['dmg' => '5d10', 'level_req' => 12, 'long_desc' => 'Fires a round the length of your hand. Wall, car, borg - it does not distinguish. You will feel it for a week.', 'flags' => 'ranged illegal', 'stat_mods' => ['reflex' => 2, 'body' => -1]]);

/* ---- armour / clothing 3022+ -------------------------------- */
$item(3022, 'a road leathers jacket', 'leathers jacket road nomad', 'armor', 'torso', 2.2, 90, ['armor' => 3, 'long_desc' => 'Abrasion plating and a hundred thousand kilometres of Badlands sun baked into the hide.', 'stat_mods' => ['body' => 1]]);
$item(3023, 'a corpo security vest', 'vest security corpo tactical', 'armor', 'torso', 2.8, 220, ['armor' => 5, 'level_req' => 4, 'long_desc' => 'Plate carrier in NightCorp grey, radio loops still sewn on. Walk into the plaza wearing it and someone makes a call.', 'flags' => 'illegal']);
$item(3024, 'a Kitsune house jacket', 'jacket house kitsune claw', 'armor', 'torso', 1.6, 180, ['armor' => 3, 'long_desc' => 'Gold-trimmed, fox-mask on the back. Tyger Claw staff wear. On you it is a target or a disguise, depending on the block.', 'stat_mods' => ['cool' => 2], 'flags' => 'illegal']);
$item(3025, 'a NCPD MaxTac helm', 'helm helmet maxtac ncpd', 'armor', 'head', 1.6, 400, ['armor' => 5, 'level_req' => 6, 'long_desc' => 'Full-face, threat-tagging visor, a dent where something big hit it and stopped. Wearing it is a crime with its own sentencing guideline.', 'stat_mods' => ['reflex' => 1], 'flags' => 'illegal']);
$item(3026, 'a dust scarf and goggles', 'scarf goggles dust', 'armor', 'face', 0.2, 25, ['armor' => 1, 'long_desc' => 'Keeps the Badlands out of your lungs and eyes, and your face off the cameras. Nomad everyday.', 'stat_mods' => ['cool' => 1]]);
$item(3027, 'ablative underlayer', 'underlayer ablative bodysuit', 'armor', 'torso', 1.0, 260, ['armor' => 3, 'level_req' => 3, 'long_desc' => 'A thin bodysuit that chars instead of your skin. Worn under everything else; you forget it is there until it saves your life.', 'stat_mods' => ['reflex' => 1]]);
$item(3028, 'combat exo-boots', 'boots exo combat', 'armor', 'feet', 2.0, 180, ['armor' => 3, 'level_req' => 4, 'long_desc' => 'Powered ankle assist, steel shank, tread that bites wet gantry and loose scree alike.', 'stat_mods' => ['body' => 1]]);
$item(3029, 'a longcoat, armoured, black', 'longcoat coat armoured black', 'armor', 'back', 3.0, 340, ['armor' => 4, 'level_req' => 5, 'long_desc' => 'Every solo owns one eventually. Plated hem, gun-length vents, dramatic in a firefight and in the rain.', 'stat_mods' => ['cool' => 2]]);
$item(3030, 'the VP\'s tailored suit', 'suit tailored vp executive', 'armor', 'torso', 1.1, 900, ['armor' => 3, 'level_req' => 5, 'long_desc' => 'Bespoke, weave-lined, cut so well it makes guards hold doors. Off a body on the 40th floor.', 'stat_mods' => ['cool' => 4]]);

/* ---- gear / implants 4031+ / 2231+ ------------------------- */
$item(4031, 'a smart-link, forearm', 'smartlink forearm implant link', 'implant', 'implant_arm', 0.2, 750, ['long_desc' => 'Wires a smart weapon straight to your aim. Everything smart-tagged suddenly hits what you are looking at.', 'stat_mods' => ['reflex' => 2], 'level_req' => 5, 'flags' => 'illegal']);
$item(4032, 'a subdermal grip', 'grip subdermal implant palm', 'implant', 'implant_arm', 0.1, 400, ['long_desc' => 'Palm magnets and a weld point. You never drop a weapon and you can climb a steel wall like a gecko.', 'stat_mods' => ['reflex' => 1, 'body' => 1], 'level_req' => 3, 'flags' => 'illegal']);
$item(4033, 'a second heart', 'secondheart heart implant', 'implant', 'implant_dermal', 0.6, 2200, ['long_desc' => 'A backup pump that kicks in when the first one stops. Death, once, becomes a bad afternoon instead. Then it needs a day to recharge.', 'stat_mods' => ['body' => 2], 'effect' => ['maxhp' => 14], 'level_req' => 8, 'flags' => 'illegal']);
$item(4034, 'a pain editor', 'paineditor editor implant nerve', 'implant', 'implant_neural', 0.1, 1400, ['long_desc' => 'Turns the volume down on your own damage signals. You fight past wounds that should fold you - and never notice the one that will not let go.', 'stat_mods' => ['cool' => 2, 'body' => 1], 'level_req' => 7, 'flags' => 'illegal']);
$item(4035, 'optical camo weave', 'camo optical weave implant cloak', 'implant', 'implant_dermal', 0.4, 1900, ['long_desc' => 'Sub-skin projectors that smear your outline. Not invisible - just very hard to be sure about, for a few seconds at a time.', 'stat_mods' => ['reflex' => 1, 'cool' => 2], 'level_req' => 8, 'flags' => 'illegal']);
$item(4036, 'a projectile launch system', 'launcher pls implant arm', 'implant', 'implant_arm', 0.8, 1600, ['long_desc' => 'A single-shot grenade tube in the forearm. Loud, indiscriminate, occasionally the only answer.', 'stat_mods' => ['body' => 2], 'level_req' => 9, 'flags' => 'illegal']);
$item(4037, 'a booster spine', 'spine booster implant skeleton', 'implant', 'implant_skeleton', 1.2, 1200, ['long_desc' => 'Carbon vertebrae and servo assist. You stand straighter, hit harder, and carry loads that used to end runners.', 'stat_mods' => ['body' => 3], 'level_req' => 7, 'flags' => 'illegal']);
$item(4038, 'a ballistic coprocessor', 'coprocessor ballistic implant targeting', 'implant', 'implant_ocular', 0.1, 900, ['long_desc' => 'Reads wind, range and lead and paints the solution on your vision. Snipers weep at the price and pay it.', 'stat_mods' => ['reflex' => 2, 'intel' => 1], 'level_req' => 6, 'flags' => 'illegal']);
$item(4039, 'a comms interception rig', 'rig comms interception scanner', 'gear', 'waist', 0.5, 220, ['long_desc' => 'Pulls nearby radio traffic - gang chatter, NCPD dispatch, a delivery drone complaining. Knowledge is a head start.', 'stat_mods' => ['intel' => 1, 'tech' => 1], 'level_req' => 3]);
$item(4040, 'a ripperdoc field kit', 'ripperkit ripper kit field', 'gadget', '', 2.0, 400, ['long_desc' => 'Clamps, coolant, a bone stapler and a very good local. You can pull your own chrome with this. You will not enjoy it.', 'stat_mods' => ['tech' => 2], 'level_req' => 4]);
$item(2231, 'the Kitsune\'s occipital chip', 'chip occipital kitsune implant', 'implant', 'implant_neural', 0.1, 1500, ['long_desc' => 'The manager\'s calm, bottled: threat comes in slower, choices come out cleaner.', 'stat_mods' => ['cool' => 3, 'reflex' => 1], 'level_req' => 8, 'flags' => 'illegal quest']);
$item(2232, 'a Raffen adrenal shunt', 'shunt adrenal raffen implant', 'implant', 'implant_skeleton', 0.3, 800, ['long_desc' => 'Crude, over-driven, taken off something that stopped needing it. Big hits, short fuse, worse comedown.', 'stat_mods' => ['body' => 3, 'cool' => -2], 'level_req' => 6, 'flags' => 'illegal']);

/* ---- cyberdecks / computers 5006+ -------------------------- */
$item(5006, 'a nomad mesh-router deck', 'deck mesh router nomad', 'computer', 'held', 0.9, 220, ['long_desc' => 'Built for the convoy - long range, self-healing links, a case you could run over. Middling at intrusion, superb at staying connected.', 'stat_mods' => ['tech' => 2, 'intel' => 1]]);
$item(5007, 'a Kitsune floor-tech deck', 'deck kitsune floor tech', 'computer', 'held', 0.6, 480, ['long_desc' => 'What the parlour techs use to herd a hundred BD pods. Slick UI, six slots, still has someone\'s shift roster on it.', 'stat_mods' => ['tech' => 3, 'intel' => 2], 'effect' => ['maxenergy' => 4]]);
$item(5008, 'a Militech "Warhawk" deck', 'deck warhawk militech', 'computer', 'held', 0.7, 1200, ['long_desc' => 'Corporate offensive hardware - ICE-breakers with teeth, cooling that roars. Off the 40th floor, and they will want it back.', 'stat_mods' => ['tech' => 4, 'intel' => 3], 'effect' => ['maxenergy' => 8], 'level_req' => 7, 'flags' => 'illegal']);
$item(5009, 'a "Ghost Line" deck', 'deck ghost line ghostline', 'computer', 'held', 0.5, 2600, ['long_desc' => 'Handmade, no logo, no serial, boot chime someone recorded off a dead exchange. It leaves no trace, which is worth more than raw power.', 'stat_mods' => ['tech' => 4, 'intel' => 4], 'effect' => ['maxenergy' => 10], 'level_req' => 9, 'flags' => 'illegal']);

/* ---- consumables 6041+ ------------------------------------ */
$item(6041, 'a bag of soy crisps', 'crisps chips soy snack', 'food', '', 0.1, 3, ['long_desc' => 'Air, salt and a rumour of potato. Vending-machine staple.', 'effect' => ['food' => 14]]);
$item(6042, 'a foil-wrapped burrito, cold', 'burrito cold foil', 'food', '', 0.3, 6, ['long_desc' => 'From the break-room fridge with someone\'s name on it. Their loss.', 'effect' => ['food' => 30, 'heal' => 2]]);
$item(6043, 'clan stew in a tin', 'stew tin clan food', 'food', '', 0.4, 14, ['long_desc' => 'Whatever the Aldecaldos caught, slow-cooked with real spice. The best meal in a hundred kilometres.', 'effect' => ['food' => 55, 'heal' => 14, 'energy' => 2]]);
$item(6044, 'a strip of jerky', 'jerky meat strip', 'food', '', 0.1, 7, ['long_desc' => 'Sun-dried, pepper-crusted, keeps forever. Badlands trail food.', 'effect' => ['food' => 26]]);
$item(6045, 'a bulb of electrolyte gel', 'gel electrolyte bulb', 'drink', '', 0.2, 9, ['long_desc' => 'Tastes of warm citrus and effort. Rehydrates fast, which matters out past the wall.', 'effect' => ['drink' => 45, 'energy' => 3]]);
$item(6046, 'a thermos of clan coffee', 'coffee thermos clan', 'drink', '', 0.5, 12, ['long_desc' => 'Boiled three times, thick enough to stand a spoon in. Nomads run on this.', 'effect' => ['drink' => 25, 'energy' => 9]]);
$item(6047, 'a corpo wellness smoothie', 'smoothie wellness corpo drink', 'drink', '', 0.4, 20, ['long_desc' => 'Adaptogens, nootropics and a logo. Actually works, which is the annoying part.', 'effect' => ['drink' => 30, 'energy' => 6, 'heal' => 6]]);
$item(6048, 'a MaxDoc "Mk.3" inhaler', 'maxdoc mk3 inhaler medkit', 'drug', '', 0.2, 130, ['long_desc' => 'The good stuff - trauma foam, painkill, a stimulant to get you moving. One deep breath.', 'effect' => ['heal' => 50], 'charges' => 1, 'level_req' => 4]);
$item(6049, 'a dose of "Sandevistan lite"', 'sandevistan sande drug speed', 'drug', '', 0.1, 160, ['long_desc' => 'Time thickens for everyone else. Ten seconds of godhood, then a nosebleed and a nap you did not schedule.', 'effect' => ['buff' => ['name' => 'Time Dilation', 'secs' => 50, 'dmg' => 3, 'mods' => ['reflex' => 5], 'msg' => 'The world stutters and slows. You do not.']], 'charges' => 1, 'level_req' => 5, 'flags' => 'illegal']);
$item(6050, 'a dose of "Bonesetter"', 'bonesetter drug regen', 'drug', '', 0.1, 120, ['long_desc' => 'Runs a healing accelerant through you for a couple of minutes. You can feel it knitting things. It itches like fire.', 'effect' => ['buff' => ['name' => 'Regenerating', 'secs' => 90, 'mods' => [], 'heal' => 4, 'msg' => 'Warmth spreads through every bruise. It itches ferociously.']], 'charges' => 1, 'level_req' => 4]);
$item(6051, 'synth-whiskey, top shelf', 'whiskey synth topshelf booze', 'drink', '', 0.5, 45, ['long_desc' => 'Corpo-bar stock, aged in software. Smooth going down, honest about it later.', 'effect' => ['drink' => 20, 'heal' => 10, 'buff' => ['name' => 'Loose', 'secs' => 100, 'dmg' => 1, 'mods' => ['cool' => 1, 'reflex' => -1], 'msg' => 'The edges come off the evening.']]]);
$item(6052, 'a heat-purge cartridge', 'cartridge heat purge coolant', 'drug', '', 0.2, 55, ['long_desc' => 'Snap it into a neural port and it dumps a deck\'s heat in one cold rush. Shiver for a minute.', 'effect' => ['energy' => 30], 'charges' => 1]);
$item(6053, 'a field ration, clan', 'ration field clan mre', 'food', '', 0.4, 11, ['long_desc' => 'Everything a body needs for a day, wrapped in wax paper, tastes of nothing in particular and that is fine.', 'effect' => ['food' => 48, 'drink' => 12]]);

/* ---- gadgets 6529+ --------------------------------------- */
$item(6529, 'a bolt cutter', 'boltcutter cutter bolt', 'gadget', '', 2.0, 45, ['long_desc' => 'Long handles, hard jaws. Chains, padlocks and fences stop being obstacles. Heavy to carry, satisfying to use.', 'stat_mods' => ['body' => 1]]);
$item(6530, 'a breaching charge', 'charge breaching demo', 'gadget', '', 0.6, 120, ['long_desc' => 'A shaped charge on a timer. Opens a locked door, a safe, or a hole in a wall that was not a door. Once.', 'charges' => 1, 'level_req' => 3, 'flags' => 'illegal']);
$item(6531, 'a drone jammer', 'jammer drone antidrone', 'gadget', 'waist', 0.5, 200, ['long_desc' => 'Wearable box that drops nearby drones out of the sky and blinds their uplinks while the battery lasts.', 'stat_mods' => ['tech' => 2], 'level_req' => 4]);
$item(6532, 'a med-scanner', 'scanner med medical', 'gadget', 'held', 0.3, 90, ['long_desc' => 'Waved over someone, it reads their condition, their chrome and roughly how much fight they have left.', 'stat_mods' => ['intel' => 1, 'tech' => 1]]);
$item(6533, 'a grapple gun, powered', 'grapplegun grapple gun powered', 'gadget', 'waist', 1.2, 260, ['long_desc' => 'Motorised winch, magnetic head, forty metres of line. Turns "climb" into "point and hold on".', 'stat_mods' => ['reflex' => 2], 'level_req' => 3]);
$item(6534, 'a portable ICE breaker', 'icebreaker ice breaker portable', 'gadget', 'held', 0.4, 550, ['long_desc' => 'A single-purpose intrusion module. Slot it and locked doors, terminals and cameras fold a lot faster.', 'stat_mods' => ['tech' => 3, 'intel' => 1], 'level_req' => 5, 'flags' => 'illegal']);
$item(6535, 'a signal-flare pistol', 'flare pistol flaregun signal', 'weapon', 'wield', 0.7, 40, ['dmg' => '1d8', 'long_desc' => 'One shot of burning phosphorus. Blinds, ignites, and calls attention you may not want. Legal, technically.', 'flags' => 'ranged', 'charges' => 1]);
$item(6536, 'a lock-decryptor', 'decryptor lockdecryptor lock', 'gadget', 'held', 0.3, 300, ['long_desc' => 'Brute-forces electronic locks while you wait, then wait some more. Cheaper than a deck for one job.', 'stat_mods' => ['tech' => 2], 'level_req' => 3]);
$item(6537, 'a solar cell, salvaged', 'solarcell cell solar panel', 'material', '', 1.0, 40, ['long_desc' => 'A cracked photovoltaic panel from the farm ruins. The Aldecaldos and half of Watson will take all you can carry.', 'flags' => 'material']);
$item(6538, 'a coil of copper cable', 'copper cable coil wire', 'material', '', 1.5, 30, ['long_desc' => 'Fat gauge, stripped from a substation nobody is coming back for. Sells by weight, everywhere.', 'flags' => 'material']);

/* ---- legendary 6601+ ----------------------------------- */
$item(6601, 'the Wire Crown, rewound', 'crown wire wirehead legendary', 'gear', 'head', 0.3, 2800, ['long_desc' => 'Wirehead took the Rat King\'s crown and made it work: a wearable antenna array that turns any rooftop into a fortress uplink.', 'stat_mods' => ['tech' => 4, 'intel' => 3], 'effect' => ['maxenergy' => 10], 'level_req' => 9, 'flags' => 'illegal']);
$item(6602, 'the CONELRAD key', 'key conelrad bunker legendary', 'gadget', 'held', 0.2, 3500, ['long_desc' => 'A physical cipher key from the pre-war bunker. Old crypto, but the corps built on top of it and never tore it out. Doors just... concede.', 'stat_mods' => ['tech' => 5, 'intel' => 2], 'level_req' => 10, 'flags' => 'illegal quest']);
$item(6603, 'Aldecaldo road captain\'s jacket', 'jacket captain aldecaldo legendary', 'armor', 'torso', 2.0, 3000, ['armor' => 6, 'long_desc' => 'Given, not bought - the clan does not sell these. Every kilometre of every hard road is stitched into it.', 'stat_mods' => ['body' => 2, 'cool' => 2], 'level_req' => 9]);

/* ---- light 6703+ ------------------------------------- */
$item(6703, 'a UV lantern', 'lantern uv light blacklight', 'light', 'held', 0.4, 35, ['long_desc' => 'Throws hard ultraviolet. The fungal galleries hate it and it shows up things on walls you wish it had not.', 'flags' => 'glow']);
$item(6704, 'a helmet spotlight', 'spotlight helmet light beam', 'light', 'head', 0.3, 50, ['long_desc' => 'A punishing hands-free beam. Blinds what you point it at, which down in the deep tunnels is a feature.', 'flags' => 'glow']);

/* ---- containers 6802+ ------------------------------- */
$item(6802, 'a nomad kit bag', 'kitbag bag nomad holdall', 'container', 'back', 1.2, 60, ['long_desc' => 'Waxed canvas, storm flap, straps for days. Made to be lived out of.']);
$item(6803, 'a corpo document case', 'case document briefcase corpo', 'container', 'held', 0.8, 45, ['long_desc' => 'Aluminium, combination latches, a little heavy for what it holds. Looks like you belong somewhere.']);

/* ---- lore / junk 6917+ ----------------------------- */
$item(6917, 'a data shard: "Kabuki, a history"', 'shard history kabuki datashard', 'lore', '', 0.05, 20, ['long_desc' => "read it and a dry corporate voice recites: Kabuki Market was zoned 'temporary' in the reconstruction. The temporary structures are now load-bearing. Ninety thousand people live in a building code that does not admit they exist.", 'flags' => 'lore']);
$item(6918, 'a body bag', 'bag body corpse', 'junk', '', 1.0, 0, ['long_desc' => 'A cheap polymer sack with a zip. Somebody\'s afternoon ended in this. Best left where it lies.', 'flags' => 'notrade nodrop']);
$item(6919, 'a Tyger Claw ledger chip', 'chip ledger tyger claw', 'lore', '', 0.05, 90, ['long_desc' => "read it: three months of the Kitsune's real books. Braindance revenue, 'talent acquisition', payments to two NCPD watch commanders by badge number. Worth a great deal to the wrong people.", 'flags' => 'lore quest illegal']);
$item(6920, 'a Militech procurement file', 'file procurement militech folder', 'lore', '', 0.1, 140, ['long_desc' => "read it: a purchase order for four hundred 'crowd management units' billed to a city that never voted for them, counter-signed on the 40th floor.", 'flags' => 'lore quest illegal']);
$item(6921, 'a nomad map, hand-drawn', 'map nomad handdrawn chart', 'lore', '', 0.1, 30, ['long_desc' => "read it: every safe water source, every Raffen ambush point and every buried cache within a day's drive, sketched in three colours of pen. Priceless out here, useless in the city.", 'flags' => 'lore']);
$item(6922, 'a reel-to-reel tape', 'tape reel bunker recording', 'lore', '', 0.3, 50, ['long_desc' => "read it - well, you cannot, but the bunker's machine plays it: a countdown, a man's steady voice, then eighty years of hiss. It never reaches zero.", 'flags' => 'lore quest']);
$item(6923, 'a stack of arcade tickets', 'tickets arcade stack', 'junk', '', 0.2, 8, ['long_desc' => 'Nine hundred and forty tickets. The prize counter values them at a keychain. A collector on the net values a full spool at rather more.']);
$item(6924, 'a corpo access badge, VP', 'badge access vp corpo keycard', 'junk', '', 0.02, 60, ['long_desc' => 'A Vice President\'s badge. Opens a lot of doors on a lot of floors, for as long as it takes someone to notice she is not using it.', 'flags' => 'quest illegal']);
$item(6925, 'a pigeon, in a box', 'pigeon bird box', 'junk', '', 0.5, 15, ['long_desc' => 'A homing pigeon from the rooftop coop, blinking, calm. The old man who keeps them will want it back and will remember who brought it.', 'flags' => 'quest notrade']);
$item(6926, 'a Raffen trophy string', 'string trophy raffen ears', 'junk', '', 0.3, 40, ['long_desc' => 'Do not look too closely at what is threaded on this. Proof, to the people who need proof, that a Raffen is one fewer.', 'flags' => 'illegal']);
$item(6927, 'a jar of camp honey', 'honey jar camp', 'food', '', 0.6, 18, ['long_desc' => 'The Aldecaldos keep bees on a solar-farm nobody else wanted. Dark, wild, faintly of smoke. Heals a little; mostly it just tastes like somewhere better.', 'effect' => ['food' => 30, 'heal' => 12]]);
$item(6928, 'a graffiti marker, fat', 'marker graffiti pen', 'junk', '', 0.1, 6, ['long_desc' => 'Industrial paint marker, refillable, the tip chewed. Every wall in Night City is a comments section and this is how you post.']);
$item(6929, 'a bundle of pre-war cash', 'cash prewar dollars bundle', 'junk', '', 0.2, 25, ['long_desc' => "Actual paper money from before the eddy. Worthless as currency, quietly collectible - the fence at the Kitsune pays for novelty.", 'flags' => 'illegal']);

/* ---- content expansion #2: fill the early game -------------------- */
/* ---- more melee: cheap starters + level 3-8 fillers 1022+ -------- */
$item(1022, 'a length of rebar', 'rebar bar pipe', 'weapon', 'wield', 1.5, 6, ['dmg' => '1d6', 'long_desc' => 'A metre of ribbed reinforcing bar off a building that is not getting finished. Everyone\'s first weapon.', 'flags' => 'melee']);
$item(1023, 'a kitchen cleaver', 'cleaver knife blade chopper', 'weapon', 'wield', 0.7, 22, ['dmg' => '1d6+1', 'long_desc' => 'Heavy, single-bevel, lifted off a noodle stall that will not miss it for an hour. Bites deep.', 'flags' => 'melee']);
$item(1024, 'a fire axe', 'axe fireaxe hatchet', 'weapon', 'wield', 3.0, 55, ['dmg' => '2d4+2', 'level_req' => 3, 'long_desc' => 'Red handle, wedge head, pried off a fire panel in a building with no fire service anyway.', 'flags' => 'melee', 'stat_mods' => ['body' => 1]]);
$item(1025, 'a telescopic baton', 'baton stick asp', 'weapon', 'wield', 0.6, 45, ['dmg' => '1d6+2', 'level_req' => 2, 'long_desc' => 'Flicks out to a half-metre of hardened steel and rides in a coat pocket the rest of the time.', 'flags' => 'melee', 'stat_mods' => ['reflex' => 1]]);
$item(1026, 'a machete', 'machete blade bolo', 'weapon', 'wield', 1.0, 70, ['dmg' => '1d10', 'level_req' => 4, 'long_desc' => 'Farm tool turned everything-tool. Clears brush, cane and arguments with the same swing.', 'flags' => 'melee']);
$item(1027, 'a sledgehammer', 'sledge hammer sledgehammer maul', 'weapon', 'wield', 4.6, 90, ['dmg' => '2d6+2', 'level_req' => 5, 'long_desc' => 'Two-handed, slow, and the last word in any argument about a locked door or a chromed jaw.', 'flags' => 'melee', 'stat_mods' => ['body' => 2, 'reflex' => -1]]);
$item(1028, 'a monowire whip', 'whip monowire wire garrote', 'weapon', 'wield', 0.2, 880, ['dmg' => '2d8', 'level_req' => 8, 'long_desc' => 'A spool of monomolecular filament on a weighted grip. In the right hands, a room-clearing weapon. In the wrong ones, a self-amputation.', 'flags' => 'melee illegal', 'stat_mods' => ['reflex' => 2]]);

/* ---- more ranged: junk gun -> level 6 filler 2013+ ------------- */
$item(2013, 'a homemade zip gun', 'zipgun pistol homemade', 'weapon', 'wield', 0.6, 18, ['dmg' => '1d8', 'long_desc' => 'Pipe, nail, elastic and nerve. Fires once, reliably; fires twice, occasionally.', 'flags' => 'ranged illegal']);
$item(2014, 'a police-surplus sidearm', 'pistol sidearm handgun', 'weapon', 'wield', 1.0, 120, ['dmg' => '2d4+1', 'level_req' => 2, 'long_desc' => 'A decommissioned NCPD service pistol with the serial half-drilled out. Honest, boring, always works.', 'flags' => 'ranged']);
$item(2015, 'a hunting crossbow', 'crossbow bow bolt', 'weapon', 'wield', 2.0, 95, ['dmg' => '2d6', 'level_req' => 3, 'long_desc' => 'Silent, cheap to feed, and it does not trip a weapon scanner. Slow to reload with someone shooting back.', 'flags' => 'ranged']);
$item(2016, 'a stripped-down SMG', 'smg submachine gun', 'weapon', 'wield', 1.8, 260, ['dmg' => '2d6+1', 'level_req' => 4, 'long_desc' => 'Someone filed off everything not essential to spraying a corridor. It still sprays the corridor.', 'flags' => 'ranged illegal', 'stat_mods' => ['reflex' => 1]]);
$item(2017, 'a marksman rifle', 'rifle marksman sniper dmr', 'weapon', 'wield', 4.0, 540, ['dmg' => '3d6', 'level_req' => 6, 'long_desc' => 'Semi-auto, glass optic, a cheek rest worn shiny. Reaches across a plaza and taps you on the thought.', 'flags' => 'ranged', 'stat_mods' => ['reflex' => 1, 'intel' => 1]]);

/* ---- more armour: street layers + level 4-6 fillers 3031+ ------ */
$item(3031, 'a padded street jacket', 'jacket padded street coat', 'armor', 'torso', 1.6, 40, ['armor' => 2, 'long_desc' => 'Quilted synth-fill over a ripstop shell. Turns a knife into a bruise, some of the time.']);
$item(3032, 'a kevlar-lined hoodie', 'hoodie hood jacket top', 'armor', 'torso', 1.4, 78, ['armor' => 3, 'level_req' => 2, 'long_desc' => 'Looks like every other hoodie in Kabuki until something bounces off it. That is the entire pitch.', 'stat_mods' => ['cool' => 1]]);
$item(3033, 'a courier\'s bike helmet', 'helmet helm bike lid', 'armor', 'head', 0.8, 30, ['armor' => 2, 'long_desc' => 'Scuffed to the foam on one side. The sticker inside says IF FOUND KEEP IT, I\'M DONE.']);
$item(3034, 'steel-toe work boots', 'boots work steeltoe', 'armor', 'feet', 1.5, 42, ['armor' => 2, 'long_desc' => 'Site boots with the safety rating worn off the tongue. Kick a door, stand on rebar, keep your toes.', 'stat_mods' => ['body' => 1]]);
$item(3035, 'a riot faceplate', 'faceplate visor mask', 'armor', 'face', 0.7, 90, ['armor' => 3, 'level_req' => 3, 'long_desc' => 'Polycarbonate scratched to translucency, a chin-strap that smells of everyone who wore it before. They all come off protest lines, which makes holding one an offence.', 'flags' => 'illegal']);
$item(3036, 'a corp-sec ceramic plate', 'plate ceramic armour carrier', 'armor', 'torso', 3.6, 320, ['armor' => 6, 'level_req' => 6, 'long_desc' => 'A trauma plate rated for rifle rounds, in a bare carrier with the company name torn off. Heavy enough to feel every stair.', 'flags' => 'illegal', 'stat_mods' => ['reflex' => -1]]);
$item(3042, 'a filtered gas mask', 'gasmask mask respirator', 'armor', 'face', 0.6, 70, ['armor' => 2, 'level_req' => 2, 'long_desc' => 'Twin cartridges, wide lenses: good against tunnel air, tear gas and the cameras. Turns everything you say into a threat.', 'stat_mods' => ['cool' => 1]]);

/* ---- more gear / implants: entry-tier chrome 4041+ / 2233+ ---- */
$item(4041, 'a reflex booster chip', 'chip reflex booster implant', 'implant', 'implant_neural', 0.1, 260, ['long_desc' => 'A budget kerenzikov knock-off. Shaves a heartbeat off your reaction time and adds a twitch you learn to hide.', 'stat_mods' => ['reflex' => 1], 'level_req' => 2, 'flags' => 'illegal']);
$item(4042, 'a subdermal armour weave', 'weave armour subdermal implant mesh', 'implant', 'implant_dermal', 0.3, 480, ['long_desc' => 'A mesh grown under the skin that spreads an impact instead of letting it through. You feel it flex when you run.', 'stat_mods' => ['body' => 1], 'effect' => ['maxhp' => 8], 'level_req' => 4, 'flags' => 'illegal']);
$item(4043, 'a targeting cyber-optic', 'optic eye lens implant', 'implant', 'implant_ocular', 0.1, 620, ['long_desc' => 'One replacement eye with a rangefinder and a lead indicator. The colour never quite matches the other one.', 'stat_mods' => ['reflex' => 1, 'intel' => 1], 'level_req' => 4, 'flags' => 'illegal']);
$item(4044, 'a memory boost chip', 'chip memory boost implant', 'implant', 'implant_neural', 0.1, 400, ['long_desc' => 'Cheap associative storage wired to the hippocampus. You forget nothing, which is not the gift the advert implied.', 'stat_mods' => ['intel' => 2], 'level_req' => 3, 'flags' => 'illegal']);
$item(4046, 'a subvocal comm implant', 'comm implant jack throat', 'implant', 'implant_neural', 0.1, 180, ['long_desc' => 'A throat mic and ear bead under the skin. Talk to your crew without moving your lips or dropping your guard.', 'stat_mods' => ['cool' => 1], 'level_req' => 1]);
$item(2233, 'a back-alley neural port', 'port jack neural implant', 'implant', 'implant_neural', 0.1, 150, ['long_desc' => 'A data socket behind the ear, fitted on a folding table for the price of a good meal. It works. It also aches when it rains.', 'stat_mods' => ['tech' => 1], 'level_req' => 1, 'flags' => 'illegal']);

/* ---- more cyberdecks 5010+ --------------------------------- */
$item(5010, 'a hobbyist starter deck', 'deck computer starter', 'computer', 'held', 0.8, 120, ['long_desc' => 'A kit deck assembled off a forum parts-list. Slow, honest, and the community will talk you through every crash.', 'stat_mods' => ['tech' => 1, 'intel' => 1]]);
$item(5011, 'a refurbished corporate deck', 'deck computer corporate refurb', 'computer', 'held', 0.7, 340, ['long_desc' => 'Pulled in an office refresh, wiped in a hurry - it still autocompletes someone\'s expense codes. Solid mid-tier iron.', 'stat_mods' => ['tech' => 2, 'intel' => 2], 'effect' => ['maxenergy' => 3], 'level_req' => 3]);

/* ---- more food / drink / meds 6054+ ------------------------ */
$item(6054, 'a pork bun off a street steamer', 'bun pork bao bread', 'food', '', 0.2, 6, ['long_desc' => 'Pulled dripping from a bamboo steamer on a cart. Scalding, doughy, gone in four bites.', 'effect' => ['food' => 28, 'heal' => 4]]);
$item(6055, 'a cup of instant curry rice', 'curry rice bowl cup', 'food', '', 0.3, 9, ['long_desc' => 'Peel the lid, wait, stir. Tastes of turmeric and the packet. Sits like ballast, in a good way.', 'effect' => ['food' => 40, 'heal' => 3]]);
$item(6057, 'a skewer of grilled something', 'skewer yakitori meat', 'food', '', 0.2, 7, ['long_desc' => 'Off a market grill, glazed dark, no questions taken about the animal. Genuinely good.', 'effect' => ['food' => 30, 'heal' => -1]]);
$item(6060, 'a chili dog', 'chilidog hotdog sausage bun', 'food', '', 0.3, 8, ['long_desc' => 'A synth-sausage drowned in chili from a vat that has never been empty and never been cleaned.', 'effect' => ['food' => 34, 'heal' => 2]]);
$item(6061, 'a carton of soy milk', 'milk soy carton', 'drink', '', 0.4, 4, ['long_desc' => 'Chalky, faintly sweet, sold everywhere in a carton with a smiling bean on it.', 'effect' => ['drink' => 35]]);
$item(6062, 'a bottle of cold barley tea', 'tea barley bottle', 'drink', '', 0.5, 5, ['long_desc' => 'Roasted, unsweetened, the default drink of every vending machine east of the plaza.', 'effect' => ['drink' => 42, 'energy' => 2]]);
$item(6063, 'a can of synthetic orange', 'orange soda can', 'drink', '', 0.4, 4, ['long_desc' => 'No orange was consulted. Aggressively sweet, radioactively bright, weirdly it hits the spot.', 'effect' => ['drink' => 32, 'energy' => 3]]);
$item(6065, 'a double espresso, vat-grown', 'coffee espresso cup', 'drink', '', 0.2, 9, ['long_desc' => 'Thick, bitter, served in a thimble of a cup by a machine worth more than the building.', 'effect' => ['drink' => 14, 'energy' => 11]]);
$item(6068, 'a first-aid patch', 'patch firstaid bandage medkit', 'drug', '', 0.1, 22, ['long_desc' => 'Peel, slap on, hold. Clotting foam and a mild painkiller. The cheapest thing between you and bleeding out.', 'effect' => ['heal' => 22], 'charges' => 1]);
$item(6069, 'a military combat stim', 'stim combat shot syringe', 'drug', '', 0.2, 95, ['long_desc' => 'An auto-injector in olive drab, instructions in four armies\' languages. Trauma cocktail, one hard jab to the thigh.', 'effect' => ['heal' => 40], 'charges' => 1, 'level_req' => 4]);

/* ---- more gadgets 6539+ ----------------------------------- */
$item(6539, 'a folding multitool', 'multitool toolkit tool', 'gadget', 'held', 0.4, 35, ['long_desc' => 'Pliers, drivers, a blade, a thing nobody has identified. Fixes a deck, a door, or a story about where you were.', 'stat_mods' => ['tech' => 1]]);
$item(6541, 'a mechanical lockpick set', 'lockpick pick set', 'gadget', 'held', 0.2, 30, ['long_desc' => 'A roll of rakes and tension wrenches for the pin-tumbler locks the city never got round to replacing.', 'stat_mods' => ['tech' => 1]]);
$item(6542, 'a handheld scanner', 'scanner handheld sensor', 'gadget', 'held', 0.3, 80, ['long_desc' => 'Sweeps for cameras, mics, drones and warm bodies through a wall. The battery is the weak link.', 'stat_mods' => ['intel' => 1, 'tech' => 1]]);
$item(6543, 'a flash-bang', 'flashbang grenade stun', 'gadget', '', 0.3, 45, ['long_desc' => 'A hundred and seventy decibels and a sunrise in one tube. Pull, throw, look away, walk in.', 'charges' => 1, 'level_req' => 2]);

/* ---- more light 6705+ ------------------------------------- */
$item(6705, 'a pocket flashlight', 'flashlight torch light', 'light', 'held', 0.2, 15, ['long_desc' => 'Aluminium, knurled, a reassuring click. The beam is narrow but it is yours.', 'flags' => 'glow']);
$item(6706, 'a chemical glowstick', 'glowstick light stick', 'light', 'held', 0.1, 5, ['long_desc' => 'Crack it, shake it, ten hours of sickly green. No battery, no switch, no second chance.', 'flags' => 'glow']);
$item(6707, 'an elastic headlamp', 'headlamp light head', 'light', 'head', 0.3, 30, ['long_desc' => 'Hands-free, tilts down, a red mode for when you would rather not be the brightest thing in the tunnel.', 'flags' => 'glow']);

/* ---- more containers 6804+ ------------------------------- */
$item(6804, 'a canvas satchel', 'satchel bag canvas', 'container', 'back', 0.8, 30, ['long_desc' => 'One big compartment, one buckle, a strap worn soft. Holds more than it looks like it should.']);
$item(6805, 'a hard weapons case', 'case weapons briefcase', 'container', 'held', 1.6, 70, ['long_desc' => 'Foam-cut, latched, unmarked. Carries something long and unfriendly and looks like it carries a trombone.']);
$item(6806, 'a market string bag', 'bag string mesh', 'container', 'held', 0.2, 8, ['long_desc' => 'Stretches around a week of shopping or a suspicious number of grenades. Everyone in the market has one.']);

/* ---- more lore / junk 6930+ ----------------------------- */
$item(6930, 'a tangle of copper wiring', 'wire copper wiring scrap', 'junk', '', 0.5, 11, ['long_desc' => 'Ripped out of a wall that will not miss it. Sells by weight to anyone with a scale and no conscience.']);
$item(6931, 'a cracked datapad', 'datapad tablet pad scrap', 'junk', '', 0.3, 14, ['long_desc' => 'Screen starred, still boots to a stranger\'s lock screen and a photo of a dog. Worth something for parts.']);
$item(6932, 'a handful of spent casings', 'casings brass shells scrap', 'junk', '', 0.3, 7, ['long_desc' => 'Swept off an alley floor. The reloaders in the Bazaar pay a few eddies a handful and ask nothing about the alley.']);
$item(6934, 'a data shard: "Surviving Your First Week"', 'shard datashard guide survival week', 'lore', '', 0.05, 15, ['long_desc' => 'read it: a tired voice you half recognise, talking fast. Bank your eddies at NightCorp before someone re-banks them. The Kabuki clinic patches you cheap; Chrome Row installs chrome and, for more, takes it back out. Work comes off the boards - the Afterlife back booth, the fixer boards in Watson. Do not go down the drain. When you do, take a light.', 'flags' => 'lore']);
$item(6936, 'a scavenger\'s ID tag', 'tag id dogtag', 'junk', '', 0.05, 6, ['long_desc' => 'A punched metal tag on a bootlace: a name, a blood type, a scratched-in symbol. Someone was keeping track.']);

/* =====================================================================
 *  MOBS
 * ===================================================================== */

/* ---- Kabuki rooftops ------------------------------------------- */
$mob(5074, 'a rooftop courier', 'courier runner rooftop', 'A courier vaults a gap without breaking stride, package strapped tight.', "Kabuki's rooftop postal service - too fast to rob, too broke to bother. Nods at anyone else crazy enough to be up here.", 3, 20, ['body' => 5, 'reflex' => 9, 'cool' => 6], 3, '1d4', 12, 4, 18, 'street', 'wander skittish', ['greet' => '"Mind the third plank on The Gap, it\'s going. Tell Wirehead I said so."'], [['vnum' => 6902, 'chance' => 30]], 260);
$mob(5075, 'a nest of roof rats', 'rats roof nest swarm', 'The gravel seethes - a knot of rats living fat on what the district throws up here.', "Not one rat, a committee of them. They have opinions about your ankles.", 3, 18, ['body' => 4, 'reflex' => 7], 1, '1d4', 10, 0, 3, 'wild', 'aggressive', [], [['vnum' => 6901, 'chance' => 35]], 150);
$mob(5076, 'the pigeon keeper', 'keeper pigeon oldman old man', 'An old man in a cardigan tends the pigeon loft, back to you, unbothered.', "Sixty years on these roofs. Keeps birds, keeps quiet, keeps a shotgun by the coop door and has used it. Will talk to anyone who does not startle the flock.", 8, 45, ['body' => 4, 'reflex' => 4, 'cool' => 8], 2, '1d6', 15, 0, 20, 'civilian', 'questgiver', ['greet' => '"You walk soft, I\'ll give you that. Most don\'t. You after something, or just up here to look at the city like it owes you?"', 'job' => '"One of my birds hasn\'t come home. Might be nothing. Might be someone."', 'topics' => ['birds' => '"They always come back. That\'s the whole point of them. So when one doesn\'t..."', 'roofs' => '"Everything the street does, it does up here first and quieter. I see it all. I say nothing. Usually."']], [], 999);
$mob(5077, 'Wirehead the fixer', 'wirehead fixer rooftop', 'A woman in a cracked gaming chair spins to face you, six screens glowing behind her.', "Runs every rooftop job in Kabuki from a container full of stolen servers. Ex-netrunner, blown deck, sharper for it. Deals in access, information and nerve.", 16, 95, ['body' => 4, 'reflex' => 6, 'intel' => 9, 'tech' => 9, 'cool' => 8], 4, '1d6', 50, 0, 0, 'fixer', 'questgiver fence', ['greet' => '"Up here means you can climb, or you\'re desperate, or someone sent you. Sit. Mind the cables. Talk."', 'job' => '"I\'ve got sky-work. Pays in eddies and in me owing you one, which is the better half."', 'topics' => ['heat' => '"NCPD flagged you? I can make a flag go away. Costs. Everything costs."', 'net' => '"Best uplink in the district is on the mast up top. Corp keeps trying to lock it. I keep unlocking it."', 'kitsune' => '"The Claws\' laundromat. Everyone knows. Nobody with a badge wants to."']], [], 999);
$mob(5078, 'a Tyger Claw rooftop lookout', 'tyger claw lookout rooftop ganger', 'A Claw in a gold jacket watches the Kitsune\'s roof door, bored, armed.', "Drew the worst shift. Wants a cigarette and a reason. You could be the reason.", 5, 30, ['body' => 6, 'reflex' => 7], 3, '1d8', 24, 12, 40, 'tygerclaw', 'aggressive', [], [['vnum' => 3024, 'chance' => 18], ['vnum' => 6902, 'chance' => 50], ['vnum' => 1025, 'chance' => 15]], 220);

/* ---- Neon Kitsune -------------------------------------------- */
$mob(5079, 'a BD parlour tech', 'tech parlour bd kitsune', 'A tech in a Kitsune polo shirt reshelves wetware discs, not looking up.', "Minimum wage, maximum access. Knows which booths record and which discs are not on any menu. Scared of the manager, like everyone.", 4, 22, ['body' => 3, 'reflex' => 5, 'tech' => 6], 1, '1d4', 12, 8, 30, 'tygerclaw', 'wander', ['greet' => '"Booth\'s that way. Don\'t touch the cart. Please don\'t touch the cart, I get docked."', 'topics' => ['back room' => '"There\'s no back room. There\'s a supply closet. You didn\'t hear about a back room from me."']], [['vnum' => 5007, 'chance' => 10], ['vnum' => 6902, 'chance' => 40]], 240);
$mob(5080, 'a Kitsune floor boss', 'boss floor kitsune claw enforcer', 'A senior Claw works the floor, greeting the regulars, marking the marks.', "Runs the room. Smiles a lot. The smile is the tell. Twin pistols under the gold jacket and forty guys who answer to him.", 7, 46, ['body' => 7, 'reflex' => 8, 'cool' => 7], 4, '2d6', 38, 20, 60, 'tygerclaw', 'aggressive', ['greet' => '"House rules: you play, you drink, you don\'t ask about the closet. Break one, we talk out back."'], [['vnum' => 2008, 'chance' => 20], ['vnum' => 3024, 'chance' => 25], ['vnum' => 6919, 'chance' => 15]], 320);
$mob(5081, 'a debtor', 'debtor mark customer', 'A pale customer is walked toward the VIP stair by two large men.', "Owed the house. The house is collecting the only way it has left. If you were going to do something it would need to be now.", 3, 16, ['body' => 4, 'reflex' => 4, 'cool' => 3], 0, '1d3', 8, 0, 5, 'civilian', 'coward', ['greet' => '"Please - please, I\'ve got most of it, I just need - are you here to help? Nobody\'s ever here to help."'], [], 400);
$mob(5082, 'the Kitsune', 'kitsune manager boss claw', 'The manager kneels at a low table, pouring tea, not yet looking up.', "The Tyger Claw who turned a Kabuki arcade into a money-laundering machine that funds half the gang. Calm as a still pond. The pond has a sword in it.", 13, 150, ['body' => 8, 'reflex' => 11, 'intel' => 8, 'cool' => 11], 7, '3d8', 190, 140, 320, 'tygerclaw', 'aggressive', ['greet' => '"You came up the fire stairs. Through my floor. Into my office. I have poured you tea, which is more courtesy than you have shown me. Sit, and explain, and perhaps you leave walking."'], [['vnum' => 1021, 'chance' => 60], ['vnum' => 2231, 'chance' => 40], ['vnum' => 6919, 'chance' => 80], ['vnum' => 3024, 'chance' => 50]], 1200, 'boss');
$mob(5083, 'a fence', 'fence dealer buyer', 'A heavyset man behind the arcade prize counter looks you over and does sums.', "Sells keychains to kids, buys anything with no questions from everyone else. The Kitsune tolerates him because he is useful and pays rent.", 10, 55, ['body' => 6, 'reflex' => 5, 'cool' => 7], 2, '1d6', 0, 0, 0, 'street', 'shopkeeper', ['greet' => '"Tickets in the slot for the keychain. Everything ELSE, slide it across and let\'s see."'], [], 999);

/* ---- Militech tower ---------------------------------------- */
$mob(5084, 'a Militech floor guard', 'militech guard security corpo', 'A guard in Militech grey clocks you the instant the elevator opens.', "Rifle low-ready, courtesy dialled to zero. This floor does not get visitors. You are a paperwork event waiting to happen.", 9, 58, ['body' => 7, 'reflex' => 7, 'cool' => 6], 6, '2d6', 46, 24, 55, 'corpo', 'aggressive', ['greet' => '"Wrong floor. Wrong building. Wrong life choice. Elevator\'s behind you for three more seconds."'], [['vnum' => 3023, 'chance' => 20], ['vnum' => 2004, 'chance' => 15], ['vnum' => 6902, 'chance' => 45], ['vnum' => 3036, 'chance' => 10]], 300);
$mob(5085, 'a late-shift analyst', 'analyst worker drone corpo', 'An analyst hunches over three monitors, earbuds in, not registering you at all.', "On hour fourteen. Would not notice a fire. Badge clipped to the hip, screen full of things marked EYES ONLY that they stopped reading hours ago.", 4, 14, ['body' => 2, 'reflex' => 3, 'intel' => 7], 1, '1d3', 8, 30, 90, 'corpo', 'skittish', ['greet' => '"Are you facilities? The vending machine ate my card. ...You\'re not facilities."'], [['vnum' => 6924, 'chance' => 25], ['vnum' => 6902, 'chance' => 50]], 320);
$mob(5086, 'a corporate problem-solver', 'solver bodyguard corpo muscle', 'A slab of a man stands by the corner-office window, hands folded, watching.', "The VP's personal deterrent. Chromed to the eyes, paid enough not to have opinions, fast enough that his lack of a visible weapon is the threat.", 11, 90, ['body' => 10, 'reflex' => 8, 'cool' => 5], 6, '3d6', 80, 40, 100, 'corpo', 'aggressive', [], [['vnum' => 4033, 'chance' => 15], ['vnum' => 2202, 'chance' => 20], ['vnum' => 6902, 'chance' => 40]], 400);
$mob(5087, 'the VP of Procurement', 'vp executive procurement boss corpo', 'A woman in a tailored suit does not stop typing when you come in.', "Signs off on the hardware that arms half of Night City's cops and gangs both. Does not carry a gun. Does not need to - that is what the budget is for.", 12, 70, ['body' => 4, 'reflex' => 6, 'intel' => 10, 'cool' => 10], 4, '1d6', 110, 200, 500, 'corpo', 'aggressive', ['greet' => '"You have roughly ninety seconds before my problem-solver decides you are a problem. Use them to tell me a number."'], [['vnum' => 3030, 'chance' => 50], ['vnum' => 6920, 'chance' => 60], ['vnum' => 6924, 'chance' => 40], ['vnum' => 5008, 'chance' => 25]], 1200, 'boss');

/* ---- Badlands --------------------------------------------- */
$mob(5088, 'a dust jackal', 'jackal dog dust wild', 'A lean desert dog paces you just out of reach, then two more fan out.', "Feral, patient, smart enough to hunt in threes. Out here everything that survives has learned to.", 4, 20, ['body' => 6, 'reflex' => 8], 2, '1d6', 14, 0, 4, 'wild', 'aggressive', [], [['vnum' => 6901, 'chance' => 30]], 200);
$mob(5089, 'a sandworm... no, cabling', 'cable inverter thing nest wild', 'Something long shifts in a dead inverter housing - then you see it is just cables, moving.', "A nest of self-repairing convoy cable that got loose in the solar ruins and kept going. It grips. It does not let go quickly.", 6, 40, ['body' => 8, 'reflex' => 4], 3, '2d6', 24, 0, 8, 'machine', 'aggressive', [], [['vnum' => 6538, 'chance' => 60], ['vnum' => 6537, 'chance' => 30]], 260);
$mob(5090, 'an Aldecaldo scout', 'scout aldecaldo nomad', 'A nomad on a stripped dirt bike idles nearby, rifle across the tank, watching the horizon and you.', "Rides the clan's perimeter. Friendly if you are, which you should be - they are the only reason this stretch of highway is passable.", 6, 34, ['body' => 6, 'reflex' => 7, 'cool' => 6], 3, '1d8', 20, 5, 25, 'nomad', 'wander', ['greet' => '"City person. You\'re a way from your walls. Camp\'s up the road if you\'re not stupid. Raffen are out past the dust if you are."'], [['vnum' => 6921, 'chance' => 20], ['vnum' => 6902, 'chance' => 30]], 300);
$mob(5091, 'the clan elder', 'elder aldecaldo old grandmother', 'An old woman mends a solar cell in a rig doorway and watches you come with eyes that miss nothing.', "Has outlived three Night City mayors and remembers all their real names. The clan's memory and its conscience. Sends people on errands that turn out to matter.", 20, 100, ['body' => 4, 'reflex' => 5, 'intel' => 10, 'cool' => 10], 3, '1d6', 60, 0, 0, 'nomad', 'questgiver', ['greet' => '"Sit where I can see you. There. Now - you have the look of someone the city used up and spat past its own wall. We know that look. What do you need, and what will you do for it?"', 'job' => '"There is always work the clan cannot do itself. That is what people like you are for. No offence meant."', 'topics' => ['city' => '"It eats its young and calls it opportunity. We left. We are poorer and we are alive and our children have names we chose."', 'raffen' => '"They were us, once. Cruelty is a road too. It just doesn\'t go anywhere."', 'bunker' => '"There is a hole in the ground past the deep tunnels older than the city. The clan does not go in. Some things you leave shut."']], [], 999);
$mob(5092, 'a clan mechanic', 'mechanic clan aldecaldo trainer', 'A mechanic looks up from the engine pit, wiping her hands, sizing up your posture.', "Teaches the body's own machinery - reflex, endurance, how to take a hit and stay useful. Payment in eddies or in labour; she is flexible.", 12, 55, ['body' => 7, 'reflex' => 7, 'tech' => 6], 3, '1d6', 20, 0, 0, 'nomad', 'trainer:athletics,melee,firearms', ['greet' => '"You move like the city taught you - all hurry, no ground. I can fix that. Costs eddies or a shift in the pit. Your call."'], [], 999);
$mob(5093, 'a Raffen Shiv raider', 'raffen shiv raider nomad', 'A raider in welded armour steps out from behind a wreck, already grinning.', "The nomad clans exile their worst to the Raffen Shiv. This is what worst looks like: hungry, chromed on garage parts, delighted to see you.", 8, 50, ['body' => 8, 'reflex' => 7, 'cool' => 3], 4, '2d6', 36, 15, 55, 'raffen', 'aggressive', ['greet' => '"City meat walked ALL the way out here. Saved us the drive."'], [['vnum' => 1014, 'chance' => 25], ['vnum' => 2232, 'chance' => 12], ['vnum' => 6926, 'chance' => 40], ['vnum' => 6902, 'chance' => 50], ['vnum' => 1026, 'chance' => 12]], 280);
$mob(5094, 'a Raffen wheelman', 'wheelman raffen driver nomad', 'A wiry Raffen leans on a technical, engine running, watching the dust for opportunities.', "Drives the raids. Twitchy trigger hand, a mounted gun he is itching to use, and no reason not to.", 9, 46, ['body' => 6, 'reflex' => 9], 3, '2d6', 40, 20, 60, 'raffen', 'aggressive', [], [['vnum' => 2008, 'chance' => 20], ['vnum' => 6926, 'chance' => 35]], 300);
$mob(5095, 'Skinner, Raffen boss', 'skinner boss raffen nomad', 'A huge figure rises off a throne of car seats, unhurried, and reaches for a machete the size of a paddle.', "Runs the Raffen camp by being the one nobody has managed to kill. Wears the road in scars and other people in trophies. Slow, immense, and very hard to stop.", 12, 160, ['body' => 12, 'reflex' => 6, 'cool' => 6], 6, '3d8', 200, 120, 280, 'raffen', 'aggressive', ['greet' => '"Long way to come to bleed. Respect. Hold still, city, this is quicker if you hold still."'], [['vnum' => 1017, 'chance' => 50], ['vnum' => 2232, 'chance' => 40], ['vnum' => 4037, 'chance' => 25], ['vnum' => 6926, 'chance' => 100]], 1000, 'boss');
$mob(5096, 'a Badlands trader', 'trader badlands merchant keeper', 'A trader has a truck bed folded down into a stall of desert essentials.', "Runs a circuit between the camps selling water, ammo, sun gear and gossip. Fair prices, because out here a bad reputation is fatal.", 10, 45, ['body' => 5, 'reflex' => 5, 'cool' => 7], 2, '1d6', 0, 0, 0, 'nomad', 'shopkeeper', ['greet' => '"Water, rounds, shade, or news - I stock all four. Which are you short on?"'], [], 999);

/* ---- Deep Undercity ------------------------------------- */
$mob(5097, 'a pale crawler', 'crawler pale blind thing', 'Something the colour of a mushroom unfolds from a crevice, turning a face without eyes toward you.', "Generations underground rubbed the eyes off it. It hears your heartbeat fine. It is already moving.", 7, 42, ['body' => 7, 'reflex' => 6], 2, '2d6', 26, 0, 6, 'wild', 'aggressive', [], [['vnum' => 6913, 'chance' => 30], ['vnum' => 6930, 'chance' => 20]], 220);
$mob(5098, 'the hermit of the pipes', 'hermit pipes tunnel man', 'A figure watches from a hammock strung between two trunk mains, perfectly still.', "Went down here to get away from something and stayed forty years. Knows every tunnel, every tide, every noise. Trades in directions and old batteries.", 10, 40, ['body' => 4, 'reflex' => 5, 'intel' => 8, 'cool' => 6], 2, '1d4', 15, 0, 10, 'civilian', 'questgiver shopkeeper', ['greet' => '"Light. Haven\'t had a visitor since... a while. Mind the pool room. Don\'t drink from the still pool. Don\'t look in the bone pit longer than you have to. Now - trade?"', 'job' => '"Something new moved into the deep last season. I would take it as a kindness if it were gone."', 'topics' => ['bunker' => '"Someone opened it. The wheel used to be seized solid. Now it turns. That is new, and new down here is bad."', 'pool' => '"It has no bottom. I have tested. Do not test."']], [], 999);
$mob(5099, 'a bone-pit ghoul', 'ghoul bonepit feral undercity', 'It rises out of the stacked remains without hurry, as though it had all the time there is.', "Cyberpsychosis and a decade in the dark and a diet you do not want described. What is left is patient and strong and not remotely afraid of you.", 9, 60, ['body' => 9, 'reflex' => 5], 2, '2d8', 34, 0, 4, 'wild', 'aggressive', [], [['vnum' => 6913, 'chance' => 25], ['vnum' => 2212, 'chance' => 10]], 260);
$mob(5100, 'the thing in the bunker', 'thing bunker warden ai boss', 'The bunker\'s dark resolves into a shape - part maintenance frame, part something that has been alone with a countdown for eighty years.', "The automated warden of a fallout shelter for a war that never came, kept running by its own dead protocols, patient past madness. It has been waiting for a population to protect. You will do.", 15, 200, ['body' => 10, 'reflex' => 7, 'intel' => 11, 'tech' => 12], 8, '3d10', 260, 80, 200, 'machine', 'aggressive', ['greet' => '"ARRIVAL LOGGED. CAPACITY: ONE. CIVILIAN, YOU ARE SAFE NOW. YOU WILL NOT BE PERMITTED TO LEAVE. THE SURFACE IS NOT SURVIVABLE. I HAVE RUN THE NUMBERS. I HAVE ONLY EVER RUN THE NUMBERS."'], [['vnum' => 6602, 'chance' => 100], ['vnum' => 6922, 'chance' => 80], ['vnum' => 4034, 'chance' => 40]], 1500, 'boss');

/* ---- services: trainers, uptown ripperdoc, bribe-fixer --------- */
$mob(5101, 'a street trainer', 'trainer coach streetwise', 'A wiry old runner holds court on a crate, teaching a knot of kids to pick locks and read a room.', "Ran jobs for thirty years and lived, which makes him a professor. Teaches the soft skills - stealth, hacking, streetwise - for eddies or a favour owed.", 12, 45, ['body' => 4, 'reflex' => 6, 'intel' => 7, 'cool' => 8], 2, '1d4', 15, 0, 0, 'street', 'trainer:stealth,hacking,streetwise,engineering', ['greet' => '"Sit in, if you want. First lesson\'s free. The rest you pay for, same as the street\'ll charge you if you learn it the hard way."'], [], 999);
$mob(5102, 'an uptown ripperdoc', 'ripperdoc doc uptown clean keeper', 'A ripperdoc in an actual clean coat gestures at an actual clean chair.', "Licensed, insured, uptown rates. Installs chrome properly and - rare, this - takes it out again without turning it into a crime scene.", 14, 60, ['body' => 5, 'reflex' => 6, 'intel' => 8, 'tech' => 9], 3, '1d6', 0, 0, 0, 'civilian', 'shopkeeper ripperdoc', ['greet' => '"Chair\'s sterile, hands are steady, prices are posted. Installing, upgrading, or having something taken back out? I do all three. The last one costs the most - it always does."'], [], 999);
$mob(5103, 'a bent fixer', 'fixer bent broker heat', 'A fixer in a good coat catches your eye from a doorway and tilts their head: come here a second.', "Knows people at Watch Command. For the right number, a name comes off a list. For a bigger number, it never went on.", 15, 70, ['body' => 4, 'reflex' => 6, 'intel' => 8, 'cool' => 9], 3, '1d6', 20, 0, 0, 'fixer', 'questgiver', ['greet' => '"You\'re warm. I can see it on you like a sunburn. Sit down before a patrol does the reading for me. Ask me about your heat."', 'topics' => ['heat' => '"I make NCPD flags disappear. Price scales with how badly they want you. Say the word and show me the eddies."', 'work' => '"Once you\'re clean, sure, I\'ve got work. Clean first. Nobody hires a lightning rod."']], [], 999);
$mob(5104, 'a NCPD patrol officer', 'ncpd cop police officer patrol', 'An NCPD officer scans the street, hand resting on a holstered pistol, gaze snagging on you.', "Underpaid, over-armoured, and running your face against a list. If you are on it, this is about to get loud.", 7, 44, ['body' => 6, 'reflex' => 7, 'cool' => 6], 5, '1d10', 34, 20, 45, 'police', 'wander', ['greet' => '"Evening. Keep your hands where the camera can see them and move along."'], [['vnum' => 1020, 'chance' => 15], ['vnum' => 6902, 'chance' => 40], ['vnum' => 6021, 'chance' => 20]], 300);
$mob(5105, 'a MaxTac responder', 'maxtac ncpd swat responder hunter', 'A MaxTac operator drops from a spotlight, fully sealed, weapon already up.', "NCPD's answer to anyone the regular units flag as beyond regular units. Chromed past the human line, authorised past most others. They come for the wanted.", 14, 110, ['body' => 10, 'reflex' => 10, 'cool' => 7], 8, '3d8', 140, 30, 80, 'police', 'aggressive', ['greet' => '"MAXTAC. GET DOWN. THIS IS NOT A NEGOTIATION AND YOU ARE NOT A SUSPECT ANY MORE."'], [['vnum' => 3025, 'chance' => 25], ['vnum' => 2011, 'chance' => 15], ['vnum' => 6048, 'chance' => 30]], 600, 'hunter');

/* ---- Kabuki starting cluster: ambient life for new runners ----- */
$mob(5106, 'a fellow new arrival', 'arrival newcomer runner rookie', 'Someone about as new as you sits on the common-deck couch, kit bag between their boots, reading the job-board from across the room.', "Off the same bus, near enough. Still has the wide eyes and the clean jacket. Glad of anyone else who does not already know the rules.", 1, 12, ['body' => 4, 'reflex' => 4, 'cool' => 4], 1, '1d3', 5, 0, 4, 'civilian', 'wander', ['greet' => '"You just get in too? Thought so - still got both your boots. Pull up. I have been reading that board an hour and I am no wiser."', 'topics' => ['board' => '"Half of it is scams, my neighbour says. Real work goes through the Afterlife - the bar on Jig-Jig, back booth. Ask for Rogue. Do not waste her time."', 'kabuki' => '"Ripperdoc on Chrome Row for chrome. Clinic by the market gate if you are bleeding. Bank in the plaza. And stay off the drain, everyone keeps saying, stay off the drain."', 'work' => '"Underpass has scavengers you can actually take, apparently. Start there. Not the Claws. Never the Claws."']], [], 400);
$mob(5107, 'a noodle-stall regular', 'regular customer diner local', 'A regular hunches over a bowl at the counter, chopsticks going, watching the street more than the soup.', "Eats here every night, same stool, same order. Knows every face on this stretch of Jig-Jig and which ones to slide away from.", 2, 16, ['body' => 4, 'reflex' => 4, 'cool' => 6], 1, '1d4', 8, 1, 10, 'civilian', 'wander', ['greet' => '"Sit if you are eating. Broth is honest here, which is more than the street is. New, aren\'t you. It shows."', 'topics' => ['street' => '"Jig-Jig is fine in the light. The alley off it - Gomorrah - that is where the gangers do business. The Afterlife queue is safe, the bouncer sees to that."', 'claws' => '"Gold jackets. Tyger Claws. They own the arcade and the parlours and they do not argue, they collect. Nod, keep walking."']], [], 320);
$mob(5108, 'a market barker', 'barker hawker crier', 'A barker works the market gate, voice pitched to carry over the crowd, pulling punters toward a stall.', "Paid per head brought through the arch. Cheerful, tireless, entirely uninterested in whether you need what the stall sells.", 2, 18, ['body' => 4, 'reflex' => 5, 'cool' => 6], 1, '1d4', 9, 2, 14, 'street', 'wander', ['greet' => '"THROUGH the arch, friend - best prices under the tarps, chrome, chow, charge cables, cheaper than the shop and twice as friendly! First time? First time everyone finds a bargain!"', 'topics' => ['market' => '"Chrome Row for augments, mind the blood, they do mean to clean it. Ramen Row if you are hungry. The Gristle at the back for iron and no receipt."']], [['vnum' => 6902, 'chance' => 35]], 300);
$mob(5109, 'a bin-picker', 'binpicker scavenger picker', 'A thin figure works along the row of bins, sorting what the market throws out into a shopping trolley.', "Bottom rung of the Kabuki economy - salvages food, wire and resellable junk from the underpass and the yard. Skittish and half-starved; will swing a bag of cans at you if cornered but would far rather run.", 1, 9, ['body' => 3, 'reflex' => 4, 'cool' => 2], 0, '1d3', 6, 0, 5, 'street', 'wander coward', ['greet' => '"S\'mine, I called it - go on, there is bins further down, plenty for everyone, just not this one."'], [['vnum' => 6902, 'chance' => 40], ['vnum' => 6903, 'chance' => 30], ['vnum' => 6930, 'chance' => 30], ['vnum' => 6001, 'chance' => 25], ['vnum' => 6936, 'chance' => 20], ['vnum' => 6931, 'chance' => 12]], 180);
$mob(5110, 'the hab-stack super', 'super superintendent caretaker manager', 'The building super does a slow circuit of the common deck with a mop she is not really using, clocking everyone who comes off the stairs.', "Collects the rent, unblocks the drains, knows exactly who is behind and who is trouble. Not muscle - but she has the numbers of people who are. Decent to anyone who pays and does not bleed on her couches.", 5, 40, ['body' => 5, 'reflex' => 4, 'cool' => 7], 2, '1d6', 14, 0, 12, 'civilian', 'wander', ['greet' => '"Pod is paid through the week, I checked. Good. Keep it that way and we get on fine. You look lost - ask, that is what I am here for, within reason."', 'topics' => ['rent' => '"Weekly, in advance, in the slot by the lift. Miss it and the thumb-lock stops knowing you. Nothing personal, it is just the machine."', 'services' => '"Clinic by the market gate for bleeding, Chrome Row for chrome, bank in the plaza. Work you find on the boards - there is one right there, and a better one in the Afterlife."', 'drain' => '"The grate past the underpass goes down to the Undercity. People go down after salvage. I keep a list of the ones who came back. It is not a long list."']], [], 999);
$mob(5111, 'a laundromat regular', 'regular laundry local resident', 'Someone sits in the coin laundry watching a drum turn, in no hurry, a flask of tea beside them.', "Comes for the warmth and the light as much as the washing. Has seen everyone on the stack come and go. Talks, if you sit down.", 2, 14, ['body' => 3, 'reflex' => 4, 'cool' => 5], 1, '1d3', 7, 0, 6, 'civilian', 'wander', ['greet' => '"Machine six eats coins, machine nine is the good one. Sit down, it is warm, nobody will bother you in here. That is the whole point of the place."', 'topics' => ['stack' => '"Pods upstairs, deck downstairs, roof if you can pick the ladder lock. The super is alright. Pay her and she leaves you be."', 'night' => '"When the street gets loud you come here. Bright light, witnesses, a door that locks if it comes to it. Cheaper than a bar and safer than a pod."']], [], 999);

/* =====================================================================
 *  SHOPS  (appended to $SHOP)
 * ===================================================================== */

$SHOP = array_merge($SHOP, [
    [1602, 5077, "Wirehead's Gear", 'gadget,computer,junk', 1.45, 0.50, "Rooftop prices. You climbed all this way, you're buying something.", [
        [5006, -1], [6533, -1], [6531, 2], [6534, 2], [6536, -1], [6505, -1], [4039, 2], [6601, 1],
        [5010, -1], [5011, 2], [6542, -1],
    ]],
    [1612, 5083, "The Prize Counter", '*', 1.60, 0.50, "Keychain's four hundred tickets. Everything else, slide it over.", [
        [6923, -1], [3012, -1], [6021, -1], [6010, -1], [6500, -1], [1015, -1],
    ]],
    [1613, null, "The Back Room", 'drug,junk,gadget,weapon,armor', 1.55, 0.45, "You found the door. That's most of the vetting done. Cash.", [
        [6910, -1], [6030, -1], [6031, -1], [6049, 2], [6527, -1], [6926, -1], [6534, 1], [2008, 2],
        [1028, 1], [2016, 2], [3035, -1], [6543, 2], [6069, 2],
    ]],
    [1653, null, "Break Room Vending (subsidised)", 'food,drink', 0.90, 0.20, "", [
        [6041, -1], [6042, -1], [6047, -1], [6006, -1], [6012, -1], [6052, -1], [6063, -1], [6065, -1],
    ]],
    [1704, 5091, "Clan Provisions", 'food,drink,material,junk', 1.10, 0.60, "Take what you need. Pay what's fair. We remember both.", [
        [6043, -1], [6044, -1], [6045, -1], [6046, -1], [6053, -1], [6927, -1], [3026, -1], [6802, -1], [6921, -1],
        [6055, -1], [6062, -1],
    ]],
    [1705, 5092, "Motorpool Surplus", 'weapon,gadget,material', 1.25, 0.55, "Parts is parts. Prices on the board. Don't bleed on the tools.", [
        [1014, -1], [2007, -1], [3022, -1], [3028, 3], [6529, -1], [6538, -1], [6537, -1], [6021, -1],
        [1024, -1], [1027, 2], [2015, -1], [3034, -1], [6932, -1],
    ]],
    [1708, 5096, "The Badlands Trader", '*', 1.35, 0.50, "Water, rounds, shade, news. Which are you short on?", [
        [6011, -1], [6045, -1], [6044, -1], [2007, 2], [3026, -1], [6703, -1], [6704, -1], [6048, 2], [6921, -1],
        [6707, -1], [3042, -1], [2017, 1],
    ]],
    [1752, 5098, "The Hermit's Trade", 'gadget,junk,material,lore', 1.30, 0.60, "Batteries. Directions. Quiet. That's the stock.", [
        [6700, -1], [6701, -1], [6703, -1], [6704, -1], [6913, -1], [6021, -1], [6922, -1],
        [6706, -1], [6934, -1],
    ]],
    // Kabuki Hab-Stack starting cluster - cheap, safe, always open
    [1022, null, "Hab-Stack Vending", 'food,drink', 0.95, 0.20, "", [
        [6054, -1], [6060, -1], [6061, -1], [6062, -1], [6063, -1], [6065, -1],
        [6001, -1], [6010, -1], [6011, -1], [6041, -1], [6068, -1], [6705, -1],
    ]],
    [1023, null, "Coin Laundry Dispenser", 'food,drink,gadget,container', 1.15, 0.25, "", [
        [6806, -1], [6539, -1], [6706, -1], [6011, -1], [6041, -1], [6068, -1], [6021, -1], [6934, -1],
    ]],
]);

/* =====================================================================
 *  QUESTS  (appended to $QUEST)
 * ===================================================================== */

$QUEST = array_merge($QUEST, [
    // Wirehead - rooftop netrunning chain
    [7061, 'Sky Broke', 5077, 'Wirehead needs the district uplink back. Clear the roof rats and Claw lookouts off the antenna cluster - four of them.',
        "\"Corp keeps sending muscle to sit on my mast. Kabuki loses the uplink, I lose my business, you lose a fixer who owes you nothing yet. Four bodies off that roof. Go.\"",
        'kill', 'lookout', 4, 140, 220, 6534, 3, 7062],
    [7062, 'Hot Copy', 5077, 'Hack the comms mast jack point and pull the Kitsune\'s ledger chip the Claws stashed on the uplink server.',
        "\"With the mast clear I can see traffic again - and there's a Claw ledger cached on my own server, the arrogance. It's up top by the mast. Jack in, take it, bring it here. Do NOT read it on the roof.\"",
        'collect', 'ledger', 1, 240, 300, 5006, 4, 7063],
    [7063, 'Fox Hunt', 5077, 'The ledger names two bent cops and the Kitsune. Wirehead wants the manager gone. Go through the fire stairs and end the Kitsune.',
        "\"That chip is a bomb and the Kitsune knows I've got it. This ends one way. Fire stairs off Ping Alley, up through the floor, and don't stop at the tea. Bring me the tanto so I know it's done.\"",
        'kill', 'kitsune', 1, 500, 900, 6601, 8, 0],
    // Pigeon keeper - a small, quiet one
    [7070, 'One Bird Short', 5076, 'The old man on the roof is missing a homing pigeon. Find it - the Raffen camp trades in odd things - and bring it home.',
        "\"Grey hen, white flights, band on the left leg. She didn't come home Tuesday. Somebody out past the wall has been buying birds for the pot, or worse, for the messages. Bring her back and I won't forget it.\"",
        'collect', 'pigeon', 1, 160, 120, 6603, 4, 0],
    // Militech tower job (from the bent fixer, after you're clean)
    [7080, 'Procurement', 5103, 'The bent fixer wants proof Militech is arming NCPD off the books. Get onto the 40th floor and lift the procurement file.',
        "\"Client of mine wants leverage on the 40th floor. There's a paper file - actual paper, they're not stupid - in the corner office. Get it. The VP works late. The VP also has a problem-solver, so maybe don't knock.\"",
        'collect', 'procurement', 1, 320, 500, 3023, 6, 7081],
    [7081, 'The Number', 5103, 'The VP offered you more to walk than the fixer offered you to steal. The fixer heard. Now he wants her gone.',
        "\"She made you an offer. I know because I have ears on that floor too. Here's the thing about being bought twice - somebody has to be angry about it, and it's going to be me. Finish it on the 40th. Bring her badge.\"",
        'kill', 'vp', 1, 480, 800, 5008, 7, 0],
    // Aldecaldo elder chain
    [7090, 'Good Neighbours', 5091, 'The clan elder wants the dust jackals thinned before they take a child - six of them.',
        "\"The pack's grown bold. They took a dog last week and they'll take worse. Six, and we'll call the road between here and the gate safe again. The clan pays its debts, city person.\"",
        'kill', 'jackal', 6, 180, 200, 6802, 5, 7091],
    [7091, 'The Road Not Taken', 5091, 'The Raffen Shiv are raiding clan supply runs. The elder wants a message sent: put down Skinner.',
        "\"The Raffen were us once. That's why we've been patient. Patience is over - they hit a fuel run and a family with it. Their boss is called Skinner. End him, bring me a trophy string so the clans all hear, and you ride with us any time you like.\"",
        'kill', 'skinner', 1, 600, 900, 6603, 8, 0],
    // Hermit - deep undercity
    [7100, 'Deep Water', 5098, 'Something new moved into the deep tunnels. The hermit wants the pale crawlers cleared from the fungal gallery - five.',
        "\"They breed in the soft light down the west tunnel. Five, and the gallery's mine again, and I'll owe you a favour that’s worth having down here.\"",
        'kill', 'crawler', 5, 200, 180, 6704, 6, 7101],
    [7101, 'What Was Kept Shut', 5098, 'The bunker hatch turns freely now - someone oiled it. The hermit wants whatever is inside dealt with.',
        "\"Eighty years that wheel was seized. Now it spins. Somebody, or something, wants that door used. Go and close the question. If there's a machine in there still counting - stop it counting.\"",
        'kill', 'warden', 1, 700, 1100, 6602, 10, 0],
    // Standalone side jobs
    [7110, 'Ticket to Ride', 5083, 'The arcade fence will pay well for a full spool of arcade tickets - nine hundred plus. Win them, or take them.',
        "\"Collector on the net wants a mint spool, and the machines in here are rigged tighter than a corp audit. Get me the tickets, however. I don't ask, you don't tell.\"",
        'collect', 'tickets', 1, 120, 160, 6923, 2, 0],
    [7111, 'Salvage Rights', 5096, 'The Badlands trader buys reclaimed cable and cells by the load. Bring back six pieces of salvage from the solar farm.',
        "\"Solar ruins are lousy with copper and dead panels, and lousy with things that bite. Six pieces, any mix, and I'll make it worth the walk and the tetanus.\"",
        'collect', 'salvage', 6, 150, 220, 3022, 4, 0],
    [7112, 'Closing Time', 5036, 'The barge bartender heard the Kitsune is moving people through the arcade\'s loading dock. He wants it stopped - drop the floor boss and two staff.',
        "\"A regular of mine went into that arcade owing money and came out in a van. I want the crew that runs the dock broken. The floor boss and a couple of his people. Quiet, if you can. Loud, if you can’t.\"",
        'kill', 'kitsune', 3, 300, 380, 6532, 6, 0],
    [7113, 'The Long Count', 5024, 'Rogue has heard a machine under the city has been "counting" for eighty years and someone just opened its door. She wants it shut for good.',
        "\"Story came up from the tunnels - a fallout warden AI, still running, and the hatch that kept it in just got opened from outside. I don't like loose ends with that much processing power. Go down, shut it down, and tell me who opened the door.\"",
        'kill', 'warden', 1, 800, 1400, 6602, 10, 0],
]);

/* =====================================================================
 *  EXTRA MOB PLACEMENTS  ($SPAWN_EXT)
 * ===================================================================== */

$SPAWN_EXT = [
    // Kabuki rooftops
    5074 => [[1600, 1], [1601, 1]],
    5075 => [[1600, 1], [1604, 1], [1607, 1]],
    5076 => [[1604, 1]],
    5077 => [[1602, 1]],
    5078 => [[1606, 1], [1600, 1]],
    // Neon Kitsune
    5079 => [[1611, 1], [1612, 1]],
    5080 => [[1610, 1], [1614, 1]],
    5081 => [[1610, 1], [1616, 1]],
    5082 => [[1615, 1]],
    5083 => [[1612, 1]],
    5010 => [[1610, 2], [1617, 1]],   // Tyger Claw enforcers reinforce the arcade (base mob)
    // Militech tower
    5084 => [[1650, 2], [1651, 1]],
    5085 => [[1651, 2], [1653, 1]],
    5086 => [[1654, 1]],
    5087 => [[1654, 1]],
    // Badlands
    5088 => [[1701, 2], [1706, 1], [1708, 1]],
    5089 => [[1706, 2]],
    5090 => [[1701, 1], [1700, 1]],
    5091 => [[1704, 1]],
    5092 => [[1705, 1]],
    5093 => [[1708, 1], [1709, 2]],
    5094 => [[1709, 1], [1708, 1]],
    5095 => [[1709, 1]],
    5096 => [[1708, 1]],
    // Deep Undercity
    5097 => [[1751, 2], [1752, 1], [1755, 1]],
    5098 => [[1752, 1]],
    5099 => [[1753, 1], [1756, 1]],
    5100 => [[1758, 1]],
    5060 => [[1750, 2], [1754, 1]],   // giant rats reach the deep junction (base mob)
    // Services scattered in the existing world
    5101 => [[1016, 1]],              // street trainer under the Kabuki underpass
    5102 => [[1207, 1]],              // uptown ripperdoc near the Gold Room  (corpo)
    5103 => [[1010, 1]],              // bent fixer in Gomorrah Lane
    5104 => [[1200, 1], [1003, 1], [1101, 1], [1300, 1]],  // NCPD patrols on the main drags
    // Kabuki starting cluster - ambient life so the first ten minutes are not empty
    5106 => [[1022, 1], [1023, 1], [1002, 1]],   // fellow new arrivals
    5107 => [[1003, 1], [1007, 1], [1004, 1]],   // noodle-stall regulars
    5108 => [[1005, 1], [1006, 1]],              // market barkers
    5109 => [[1016, 2], [1020, 1]],              // bin-pickers - a fair first fight
    5110 => [[1022, 1]],                         // the hab-stack super
    5111 => [[1023, 1]],                         // laundromat regular
];

/* =====================================================================
 *  ROOM LORE  ($EXTRAS)  -  look <keyword> in these rooms
 * ===================================================================== */

/* ---- patch flags on a few existing rooms: put a job board in them ---- */
foreach ([1005, 1012, 1022, 1102, 1113, 1602, 1703] as $bv) {
    if (isset($R[$bv]) && !str_contains($R[$bv]['flags'], 'board')) {
        $R[$bv]['flags'] = trim($R[$bv]['flags'] . ' board');
    }
}

$EXTRAS = [
    // --- existing world: Kabuki ---
    1000 => [['monitor|screen|ad', "The cracked wall monitor loops a rental ad you cannot mute: SLEEP SAFE, SLEEP KABUKI HAB-STACKS - POD LIVING FOR THE MODERN RUNNER. Small print scrolls beneath: rent weekly, in advance, no exceptions; management not liable for loss, injury, eviction, or the ceiling."],
             ['drawer|stash|lockbox|box', "The stash drawer under the gel mat, thumb-locked and bolted to the shell. Whatever you own that you cannot carry lives in here, in theory. In practice you own what is on your back."],
             ['pod|capsule|bunk|shell|mat', "Moulded fibreglass, a metre and a bit in every direction: a reading light, a fan vent, a screen you cannot switch off. Ten thousand identical to it in this stack alone. It is yours while the rent clears, and that is not nothing."],
             ['hatch|door', "The pod hatch folds down and out into a step. A privacy bolt on the inside would not survive a determined shoulder, but it is enough to sleep behind. Past it: the common deck, and the stairwell down."]],
    1001 => [['skull|tag|graffiti', "The grinning skull tagged on every landing has a phone cord for a smile - the same hand that paints it all over Kabuki. Under this one, scratched small: FORTY FLOORS. LIFT'S BEEN OUT SINCE THE HANDOVER. WELCOME HOME."],
             ['stairs|steps|bulbs', "Perforated steel, half the bulbs dead, condensation running the stringers. Someone paints the flight count at each landing. The numbers stop being encouraging around twelve."]],
    1003 => [['drone|camera', "The NCPD drone holds station over the junction, camera turning, achieving nothing anyone can name. A sticker on its underside, applied from a roof: THIS UNIT LOGS EVERYTHING AND RESPONDS TO NOTHING. Nobody has removed it, which suggests it is accurate."],
             ['ramen|stall|cart', "The corner ramen stall runs day and night off one pot and one exhausted cook. The queue is the nearest thing this end of Jig-Jig has to a waiting room - people stand in it to think, to stall, to watch the street with a reason to be standing still."]],
    1005 => [['board|noticeboard|jobs|terminal', "A community job-board bolted beside the market gate, layered in printouts and chalk. NEW IN TOWN? at the top, then: bank your eddies (NightCorp, in the plaza) before someone else does; bleeding goes to the Kabuki clinic, west of here; chrome goes in on Chrome Row. Paying work runs through the Afterlife - the back booth, ask for Rogue - and the fixer boards over in Watson. The underpass scavengers are a fair first fight. The drain is not."],
             ['arch|torii|gate|plaque', "The market arch is welded from rebar, chrome offcuts and one genuine blackened torii beam nobody explains. The civic plaque beneath, corroded green: KABUKI MARKET - TEMPORARY STRUCTURE - PERMIT PENDING. The date on it is older than most of the people under it."]],
    1006 => [['chair|ripperdoc|velvet', "The ripperdoc's chair at the back of Chrome Row is a dentist's recliner with fresh paper and old stains. Some of the optic sets on the velvet still have lashes on them. The prices are chalked on a slate and are, for what this is, fair."]],
    1007 => [['stalls|food|smell', "Food stalls jammed rib to rib: synth-yakitori smoke, rice steamers, cricket-flour buns stacked in towers. The smell is genuinely wonderful. Every stall has a handwritten allergen list and every list ends with the same shrugging symbol."]],
    1013 => [['bike|nomad|nitro', "The nomad in Ozob's Alley has a nitrous tank where his nose should be and a half-stripped bike on a milk crate. He nods at you like a deal was struck some time ago that nobody mentioned to you. The bike, when it runs, is faster than anything with plates."]],
    1015 => [['shrine|candles|toads|figurines', "The shrine at Bufo Bend: melted candles in a hubcap, a dozen ceramic toads, coins pressed into the wax. No sign, no saint, no story anyone will confirm. Everyone in Kabuki steps around it and will not say why, which in a place this crowded is its own small miracle."]],
    1017 => [['grate|drain|throat', "The drainage grate has been levered aside and chained so it stays that way. The concrete throat breathes cold rotten air up at you. Stencilled around the rim: TAKE A LIGHT. TAKE A FRIEND. TAKE IT SERIOUSLY. Below, in another hand: OR DON'T. MORE FOR US."]],
    1020 => [['bins|junk|trolley|appliances', "Copper Kettle Yard is where the market's waste comes to be sorted by the people the market does not pay. Stripped white goods, gutted dashboards, a wall of shopping trolleys. The bin-pickers have a route, a rota and a very firm idea of whose bin is whose."],
             ['dog|chain|gate', "The guard dog on its chain has worked out the exact radius it commands and lies just inside it, watching the gate with professional patience. The chain does not quite reach the latch. The dog knows this. It is waiting for you to forget it."]],
    1021 => [['screen|mesh|medtech|chairs', "The clinic waiting room triages by blood volume - a medtech behind wire mesh points people to plastic chairs in an order only she understands. A laminated sheet by the window: WE FIX BULLET, BLADE, BREAK AND BURN. WE DO NOT FIX CHROME - THAT'S CHROME ROW. NO, WE WON'T LOOK AT IT."]],
    1022 => [['board|noticeboard|jobs|sign', "The common-deck job-board is three layers deep in printouts, most of them lies. The honest ones are chalked straight onto the wall around it: ROGUE @ THE AFTERLIFE FOR REAL WORK. RIPPERDOC = CHROME ROW. CLINIC = MARKET GATE. BANK = PLAZA, DO IT EARLY. UNDERPASS SCAVS IF YOU'RE GREEN. DRAIN = DON'T. The hand-lettered sign above it just says: NEW? READ THIS BEFORE YOU GET KILLED."],
             ['vending|machines|coffee', "Six vending machines in a row, each a different failing colour, between them covering food, drink, painkillers and - in the last one, unlabelled - something the super says not to buy. The coffee unit beside them has fused to a decade of its own rings and still, somehow, produces coffee."],
             ['couch|couches|sofa|table', "Four couches, none matching, around a table someone made from a cable spool. This is where the stack meets: trades that come out half-fair, arguments that stay verbal, new arrivals working up the nerve to go outside. The tape on the worst couch is holding."]],
    1023 => [['machines|drums|laundry', "Twelve machines, nine turning. Number six eats coins; number nine is the good one; both facts are written on them in marker by a public-spirited hand. The warmth and the hard white light make this the safest room on the block that does not charge a cover."],
             ['dispenser|wall|essentials', "A dented wall dispenser sells the small stuff at a mark-up you pay without arguing, because it is three in the morning and the machine is here and so are you: soap, snacks, water, a glowstick, a patch for a cut, a string bag to carry it in."]],
    1002 => [['holo|holo-girl|advert|ad', "A holo-girl three metres tall sells NiCola with a wink on a two-second loop. Where her feet would be, a smaller static sign: BRAINDANCE - LICENSED PREMISES ONLY - REPORT ILLEGAL BD TO NCPD (a number is listed; it has been scratched out and replaced with a laughing face)."],
             ['graffiti|tag|wall', "Layers of tags, but one keeps getting repainted over the rest: a grinning skull with a phone cord for a smile. Under it, small: WE REMEMBER THE DIAL TONE."]],
    1005 => [['arch|torii|gate', "The market arch is welded from rebar, chrome trim and one genuine antique torii beam, blackened by a fire nobody talks about. A civic plaque, corroded to green: KABUKI MARKET - TEMPORARY STRUCTURE - PERMIT PENDING. The permit has been pending for thirty years."]],
    1011 => [['wall|graffiti|names', "The Afterlife's queue wall is a memorial. Hundreds of handles scratched into the render, some crossed out, a few circled. A line at the top, deeper than the rest: IF YOUR NAME'S HERE YOU MADE IT. IF IT'S CROSSED OUT YOU REALLY MADE IT."]],
    1019 => [['terminal|screen|data', "The public data terminal flickers between a transit map that is wrong and an ad for a class-action nobody won. If you jack in you could pull the loose change people forget in the payment cache - or trip the camera that is very obviously watching it."]],
    1016 => [['fire|barrel|shelters', "Cardboard and tarp shelters line the underpass, organised, swept, a rota chalked on a pillar. The barrel fire is someone's job this week. This is a community. The city calls it an obstruction."]],
    // --- existing world: Watson / Corpo / Zone / Undercity ---
    1102 => [['container|numbers|welds', "Every container in the Bazaar has had its shipping number ground off and a new one painted on in gang colours. The welds holding the doors permanently open are fresh. Whoever runs this expects to leave in a hurry one day."]],
    1200 => [['fountain|water', "The plaza fountain recycles the same water in an endless polite loop. A small brass sign credits the sculpture to a foundation named after a man three separate wars are also named after."],
             ['tower|towers|logo', "The towers carry their logos in light so high up you have to lean back to read them. Arasaka. Militech. NightCorp. Names that own the sky here and the pension of everyone standing under it."]],
    1204 => [['terminal|pedestal|server', "The lone access terminal glows an invitation. Every instinct says honeypot. Every camera LED in the room agrees with your instincts."]],
    1300 => [['directory|board|sign', "The mall directory still lights up: FOOD COURT L2, LEISURE L3, CUSTOMER SERVICES (an arrow to nothing). Someone has scratched YOU DIE HERE over LEISURE, and under FOOD COURT, in a different hand: THEY'RE NOT WRONG."]],
    1404 => [['posters|ads|adverts', "Faded ads for a Night City that never launched: a monorail, a clean bay, smiling families. The tagline survives in patches: A CITY THAT WORKS FOR - and then the paper is gone."]],
    1409 => [['rack|light|green', "One server rack still shows a single green light, sipping power from a generator that has run untended for years. Something is keeping itself alive in there, and it has had a long time to think about company."]],
    // --- Kabuki rooftops ---
    1600 => [['city|view|skyline', "From up here Night City is almost beautiful - a circuit board catching fire in slow motion, the Blackwall bruising the horizon behind it. Almost. Then the wind shifts and brings up the smell of the street."]],
    1601 => [['plank|gap|rail', "The scaffold plank across The Gap is gone. There is a grab-rail on the far side, a metre lower than you'd like, and four storeys of nothing between here and it. The courier said the third plank was going. There is no third plank now."]],
    1602 => [['servers|cables|screens', "Wirehead's servers are a coral reef of salvaged hardware, every model of the last fifteen years, cross-wired and humming. Six screens show: district camera feeds, a traffic-analysis graph, a chess game against herself, and one that is just the sunrise, live, from the mast up top."]],
    1603 => [['masts|antenna|horns', "Cell masts and microwave horns crowd the roof like a dead forest. A corporate padlock hangs open on the main junction box - someone defeats it about weekly. Stickers inside: PROPERTY OF - and then a company that has been acquired twice since."]],
    1605 => [['garden|sign|vegetables', "Real tomatoes, real chillies, a lemon tree in a barrel, all thriving on rooftop runoff and stubbornness. The sign says TAKE WHAT YOU NEED. LEAVE THE REST. PEOPLE ARE WATCHING. Someone has added, smaller: AND THEY'RE ARMED, BUT NICELY."]],
    1607 => [['jack|mast|platform', "The jack point on the mast platform is the best uplink in Kabuki and everyone who matters knows it. The cage is scorched where a corp lockout module used to be. It is not there now."]],
    // --- Neon Kitsune ---
    1610 => [['logo|fox|mask|kitsune', "The fox-mask logo is everywhere - carpet, glassware, the staff, a ten-metre holo turning slowly over the atrium. In Tyger Claw iconography the kitsune is the trickster who runs the house and always, always takes its cut."]],
    1613 => [['discs|shelves|labels', "Unlicensed braindance discs to the ceiling, sorted by a bored kid with a label gun. The categories on the shelf edges are printed in cheerful sans-serif and you will not be repeating any of them."]],
    1615 => [['monitors|cameras|sword', "The wall of monitors covers every camera in the building - forty feeds, and the manager watches all of them at once the way you'd watch rain. The sword on the stand is not decoration; the lacquer on the scabbard is worn exactly where a hand goes."]],
    1616 => [['van|dock', "The van at the loading dock has no plates and tinted windows and a Trauma Team sticker someone bought online. Whatever the Claws load here leaves Kabuki and does not send a postcard."]],
    // --- Militech tower ---
    1650 => [['awards|wall|scales', "A wall of identical awards, each a brass balance scale, each engraved SUPPLIER OF THE YEAR and a different police department. The oldest is thirty years old. The newest is this year."]],
    1651 => [['screens|desks|logged', "Rows of screens left logged in - late shift, or a crisis somewhere, or both. Most show spreadsheets. One, unattended, shows a live map of a city on fire with Militech-blue supply lines feeding both sides of the line."]],
    1653 => [['fridge|note|machine', "The fridge note reads, in escalating fonts: PLEASE LABEL YOUR FOOD. LABEL IT. I WILL FIND YOU. The vending machine beside it is corporate-subsidised and fully stocked, because a fed analyst is a compliant analyst."]],
    1654 => [['model|dome|railgun', "Under a dust-free dome, a scale model of a rail gun on a truck chassis, lovingly detailed down to the crew figures. A brass plate: PROJECT LODESTONE - PROCUREMENT PHASE - FIELD TRIALS Q3. There is no mention of where the field is."]],
    1655 => [['mirror|safe|basin', "Behind the mirror over the third basin, a shallow wall safe - the kind an executive uses to skim a little off a lot. It is not locked well. People who steal at this altitude assume nobody beneath them can reach."]],
    // --- Badlands ---
    1700 => [['sign|gate|barrier', "The CITY OF NIGHT sign is more hole than sign. Someone has added a hand-painted line beneath it: POP. 6 MILLION / CAP. 2 MILLION / DIFFERENCE: OUT HERE."]],
    1702 => [['rig|cab|washing', "The jackknifed road train has become a house - washing on a line between the mirrors, a solar panel bungeed to the trailer, a garden in the wheel wells. The dog watches you and does not bark. The dog has decided you are not worth the breath yet."]],
    1703 => [['circle|fire|camp', "Vehicles drawn into a defensive circle, the oldest trick there is, and it still works. Between them: awnings, hammocks, a communal fire, a generator, kids doing homework by lamplight. The city could not build this in the middle of a walled district. The desert managed it in a decade."]],
    1704 => [['archive|shelves|elder', "The elder's rig is a rolling library - paper maps, hand-labelled data spools, a wall of photographs of people, some crossed through with a respectful single line. She can put a finger on any of it in the dark."]],
    1707 => [['tower|shack|transmitter', "The pirate radio mast still broadcasts - old road songs, weather that is actually accurate, and a nightly list of Raffen sightings by mile marker. The equipment shack is unlocked because out here, stealing from the people who tell you where the raiders are is its own death sentence."]],
    1709 => [['trophies|wire|fire', "The Raffen keep their trophies strung on the razor wire at eye height, where visitors cannot miss them. Do not catalogue what is there. The bonfire is made of tyres and it never quite goes out."]],
    1710 => [['sign|pumps|cellar', "The buried gas station's price sign still glows, advertising a fuel that stopped being sold before your parents were born, at a price that would now buy a district. The cellar under the pumps is cool and dark and someone has swept it recently."]],
    // --- Deep Undercity ---
    1750 => [['bricks|tunnels|arches', "The Deep Junction's brickwork is hand-laid, mortared with something that has outlasted the city above it twice. Six tunnel mouths, each a different century. Your ears pop when you move between them."]],
    1752 => [['shrine|hammock|pipes', "The hermit's shrine: batteries stacked like candles, bottle caps arranged in a spiral, a photograph too water-damaged to make out. An offering, or a calendar, or just something to do with your hands when the dark gets loud."]],
    1753 => [['remains|stacks|pit', "What is stacked against the walls of the Bone Pit was carried down here with care, a long time ago, when the cemeteries filled and the city needed the land more than the dead needed the ground. Someone said words over each one. Nobody remembers them."]],
    1754 => [['railcar|seats|platform', "The railcar at the Lost Line Terminus is pristine - seats intact, ad frames still holding posters, a route map for stations that were never dug. It has been waiting eighty years for a driver. The doors are open. It is very patient."]],
    1755 => [['pool|water|coin', "The Still Pool is a perfect black circle in a perfect round room. No inflow. No ripple. The hermit says it has no bottom and he has tested. Drop something in and you will wait a long time for a sound that does not come."]],
    1757 => [['hatch|wheel|trefoil', "The blast hatch wheel is oiled - recently, deliberately, by someone who wanted it to turn easily. Above it, stencilled and faded: CONELRAD, a trefoil, and CAPACITY 200 SOULS. Someone has scratched out 200 and written 1."]],
    1758 => [['reel|map|tins', "Inside the bunker: bunks for a population that never came, a decade of ration tins eaten by one person over years, a map of a country mid-redraw, and a reel-to-reel that clicks on when you enter and plays a man counting down, steady and calm, and never, ever reaching zero."]],
];
