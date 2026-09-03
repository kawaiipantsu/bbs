<?php

/**
 * Hackers-MUD world builder.
 *
 *   php mysql/mud_world.php          # build / rebuild the world
 *   php mysql/mud_world.php --stats  # just print counts
 *
 * Re-runnable: it wipes the world/template tables and re-seeds them from the
 * data below. Player characters (mud_players / skills / quests progress) are
 * kept; their equipment and effects are cleared and they are moved to the
 * start room so stale instance ids can't dangle.
 *
 * vnum ranges
 *   rooms   1000-1999 (per zone in 100s)
 *   items   1000-6999  (melee 1000s · ranged 2000s · armour/clothing 3000s ·
 *                       gear/implants 4000s · decks/computers 5000s ·
 *                       consumables 6000s · gadgets 6500s · misc/quest 6900s)
 *   mobs    5000-5999
 *   quests  7000-7999
 */

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use Bbs\Core\Db;

$statsOnly = in_array('--stats', $argv, true);

/* ============================================================
 *  DATA
 * ============================================================ */

/** zones: slug => [name, desc, lvmin, lvmax] */
$ZONES = [
    'kabuki'   => ['Kabuki',              'A wet neon slum stacked twelve storeys of scaffolding high. Where every runner starts.', 1, 6],
    'watson'   => ['Watson Docks',        'Container stacks, rusted cranes and the open-air Bazaar. Smugglers run it.', 3, 10],
    'corpo'    => ['Corpo Plaza',         'Glass towers, marble lobbies and private security with no sense of humour.', 5, 16],
    'zone'     => ['The Combat Zone',     'A dead shopping arcade the city gave up on. Scavs nest in the food court.', 9, 20],
    'undercity'=> ['The Undercity',       'Maintenance tunnels, storm drains and things that learned to live down here.', 6, 18],
    'blackwall'=> ['The Blackwall Fringe','Where the old Net rots against the wall that keeps the rogue AIs out.', 16, 30],
    'arcade'   => ['The Neon Kitsune',    'A three-floor braindance parlour and arcade off Jig-Jig. The Tyger Claws launder half of Kabuki through it.', 2, 12],
    'badlands' => ['The Badlands Edge',   'Past the city limits: dead highway, solar-farm bones and the nomad clans who make the desert work.', 6, 20],
];

/**
 * rooms: vnum => [zone, name, x, y, z, description, flags]
 * flags: safe, dark, shop, bank, indoors, nomob, start
 */
$R = [];
$room = function (int $vnum, string $zone, string $name, int $x, int $y, int $z, string $desc, string $flags = '') use (&$R) {
    $R[$vnum] = compact('zone', 'name', 'x', 'y', 'z', 'desc', 'flags');
};

/* ---- Zone: KABUKI (1000-1099) ------------------------------------- */
$room(1000, 'kabuki', 'Your Sleep Pod - Kabuki Hab-Stack', 0, 0, 0,
    "A rented capsule the size of a generous cupboard: a moulded fibreglass shell, a gel mat that has met a thousand backs, a fan that ticks, and a cracked wall monitor cycling ads you cannot switch off. A thumb-locked stash drawer is bolted under the mat. Your name is on the rental for as long as the credits clear. It is, by Night City standards, safe.\nThe pod hatch folds down onto the common deck and the stairwell, DOWN.", 'safe indoors nomob start');
$room(1001, 'kabuki', 'Hab-Stack Stairwell', 0, 1, 0,
    "Forty flights of perforated steel, half the bulbs dead, all of it sweating condensation. Someone has tagged every landing with the same grinning skull. UP goes to the sleep pods and the common deck, DOWN spills onto the street; a short corridor runs EAST to the deck itself.", 'indoors');
$room(1002, 'kabuki', 'Jig-Jig Street', 0, 2, 0,
    "The spine of Kabuki. Braindance parlours, noodle carts and holo-girls three metres tall selling things that are illegal in four jurisdictions. Rain turns the neon into a river of colour underfoot.", 'safe');
$room(1003, 'kabuki', 'Jig-Jig Street, North End', 0, 3, 0,
    "The crowd thins where Jig-Jig meets the market gate. A NCPD drone hovers, camera swivelling, doing nothing. A ramen stall steams on the corner.", 'safe');
$room(1004, 'kabuki', "Wakako's Noodle Bar", 1, 3, 0,
    "Eight stools around a hot steel counter. The broth has been simmering since before you were born and it shows. The cook never looks up from the pot.", 'safe indoors shop');
$room(1005, 'kabuki', 'Kabuki Market Gate', 0, 4, 0,
    "A torii arch someone welded out of rebar and chrome. Past it the covered market roars - a hundred stalls under a patchwork of tarps and stolen billboard vinyl.", 'safe');
$room(1006, 'kabuki', 'Kabuki Market - Chrome Row', -1, 4, 0,
    "Stalls of second-hand cyberware laid out on velvet like jewellery. Optic sets still crusted with someone's blood. A ripperdoc works a chair at the back.", 'safe shop');
$room(1007, 'kabuki', 'Kabuki Market - Ramen Row', 1, 4, 0,
    "Food stalls shoulder to shoulder: synth-yakitori, real-ish rice, cricket-flour buns. The smell is fantastic and you should not think too hard about any of it.", 'safe shop');
$room(1008, 'kabuki', 'Kabuki Market - The Gristle', 0, 5, 0,
    "The far corner where the market stops pretending. Unlicensed iron, hot cyberdecks, ammo by the handful. Nobody asks your name here.", 'shop');
$room(1009, 'kabuki', "Pawnshop 'Last Resort'", -1, 5, 0,
    "Bulletproof glass, a slot tray, and a proprietor who has seen everything walk through it twice. Buys anything. Pays like it.", 'safe indoors shop');
$room(1010, 'kabuki', 'Gomorrah Lane', 1, 2, 0,
    "A side alley off Jig-Jig, strung with dead fairy lights. A back door thumps with bass. Puddles you would not step in on a bet.", '');
$room(1011, 'kabuki', "The Afterlife Queue", 2, 2, 0,
    "A line of fixers, mercs and hopefuls against a graffitied wall, waiting to get into the only bar in Night City that matters. The bouncer is a wall of muscle and chrome.", 'safe');
$room(1012, 'kabuki', "The Afterlife - Back Booth", 3, 2, 0,
    "Inside, finally. Drinks named after dead legends. A fixer holds court in the corner booth, drink untouched, eyes on the door.", 'safe indoors');
$room(1013, 'kabuki', 'Ozob\'s Alley', -1, 2, 0,
    "Narrow enough to touch both walls. A nomad with a nitro tank where his nose should be tinkers with a bike. He nods like he knows you.", '');
$room(1014, 'kabuki', 'Wire Street', -1, 1, 0,
    "Overhead the district's stolen power hangs in bundles thick as a torso, sparking where the insulation gave up. Step around the wet patches.", '');
$room(1015, 'kabuki', 'Bufo Bend', -2, 1, 0,
    "Where Wire Street kinks around a collapsed storefront. Someone keeps a shrine of melted candles and toad figurines here. Nobody knows why. Everybody leaves it alone.", '');
$room(1016, 'kabuki', 'Kabuki Underpass', 0, 6, 0,
    "The market backs onto a road tunnel. Traffic booms through above. Cardboard shelters line the walls; a barrel fire throws shadows. Trouble sleeps here during the day.", '');
$room(1017, 'kabuki', 'Drain Mouth', 0, 7, 0,
    "The underpass drainage grate has been levered aside. A round concrete throat breathes cold, rotten air. It goes DOWN into the Undercity. Only desperate people go down there.", '');
$room(1018, 'kabuki', 'Rooftop - Kabuki Hab-Stack', 0, 0, 1,
    "Up a maintenance ladder onto tar paper and satellite dishes. The whole glittering wound of Night City lies out past the parapet. A cell tower hums. Good place to think, or to jack in quietly.", '');
$room(1019, 'kabuki', 'Ping Alley', 2, 3, 0,
    "A dead-end stub of concrete behind the braindance parlours. A public data terminal is bolted to the wall, screen flickering an invitation. Cameras everywhere.", '');
$room(1020, 'kabuki', 'Copper Kettle Yard', 2, 4, 0,
    "A junk yard of stripped appliances and gutted vehicles behind the market. Rats the size of cats. A guard dog on a chain that does not quite reach the gate.", '');
$room(1021, 'kabuki', 'Kabuki Clinic - Waiting Room', 1, 1, 0,
    "Trauma Team will not come to Kabuki, so Kabuki made its own. Plastic chairs, a coughing crowd, a medtech behind a mesh screen who triages by how much you bleed.", 'safe indoors');

/* ---- Zone: WATSON DOCKS (1100-1199) ----------------------------- */
$room(1100, 'watson', 'Kabuki-Watson Overpass', 0, 0, 0,
    "A pedestrian bridge over a dead canal joins Kabuki to the docks. Halfway across the neon stops and the sodium lights begin. The air turns to salt and diesel.", '');
$room(1101, 'watson', 'Dogtown Cut', 0, 1, 0,
    "A slash of road between container walls four high. Gang tags layered ten deep. Something skitters off the moment you look at it.", '');
$room(1102, 'watson', 'The Bazaar - Main Aisle', 0, 2, 0,
    "The Watson black market, run out of shipping containers with their doors welded open. Everything the licensed shops will not sell you, at twice the price and no receipt.", 'shop');
$room(1103, 'watson', 'The Bazaar - Iron Container', -1, 2, 0,
    "A forty-foot container racked wall to wall with weapons. A woman with a shotgun prosthetic for a left arm runs it. She does not haggle.", 'shop');
$room(1104, 'watson', 'The Bazaar - Chrome Container', 1, 2, 0,
    "Cyberware that fell off a corporate truck, still in factory foam. A ripperdoc in a butcher's apron will install it right here on a folding table.", 'shop');
$room(1105, 'watson', 'The Bazaar - Apothecary', 0, 3, 0,
    "Stims, boosters, combat drugs and things with no name, arranged by colour. The chem-cook wears a respirator and rubber gloves and never takes either off.", 'shop');
$room(1106, 'watson', 'Container Maze - East', 1, 1, 0,
    "The stacks form corridors that change weekly as the cranes shuffle them. Easy to lose someone in here. Easier to get lost.", '');
$room(1107, 'watson', 'Container Maze - Dead Drop', 2, 1, 0,
    "A container with its number filed off, padlock hanging open. Fixers use it to pass packages without meeting. Right now it is empty. Probably.", '');
$room(1108, 'watson', 'Warehouse 7 - Loading Bay', 0, 4, 0,
    "Roll-up doors, forklift skid marks, a smell of machine oil and old fish. Maelstrom gangers use it as a clubhouse and they are not welcoming.", '');
$room(1109, 'watson', 'Warehouse 7 - Gantry', 0, 5, 1,
    "Steel walkway around the warehouse ceiling, twenty metres up. From here you can see the whole floor and everyone on it before they see you.", '');
$room(1110, 'watson', 'Warehouse 7 - Boss Office', 0, 6, 1,
    "A site manager's office turned throne room. Monitors showing every camera in the docks. A chair made of welded gun parts, and the ganger who sits in it.", 'indoors');
$room(1111, 'watson', 'Pier 9', -1, 4, 0,
    "Rotten decking over black water. A tug boat lists at its mooring. Gulls the size of drones argue over something on the planks.", '');
$room(1112, 'watson', 'Pier 9 - Tug Boat', -2, 4, 0,
    "The wheelhouse smells of tar and cold coffee. Charts of the coast, a radio that still crackles, a bunk someone left in a hurry.", 'indoors');
$room(1113, 'watson', "Smuggler's Rest", -1, 3, 0,
    "A bar in a half-buried barge hull. No sign, no name, no questions. The bartender keeps a harpoon gun under the counter and a soft spot for people who tip.", 'safe indoors shop');
$room(1114, 'watson', 'Wire Yard', 1, 4, 0,
    "Spools of stolen fibre-optic taller than a person. A tech sorts through them with a torch, muttering bandwidth figures like prayers.", '');
$room(1115, 'watson', 'Silicon Gulch', 1, 5, 0,
    "The docks' e-waste dump. A mountain range of dead terminals, dashboards and drones. Kids strip it for gold by day; other things work it by night.", '');
$room(1116, 'watson', 'Drain Confluence', 1, 6, 0,
    "Three storm drains meet under a broken jetty. The middle one is big enough to walk into and goes DOWN toward the Undercity. The water is not water.", '');
$room(1117, 'watson', 'Watson-Corpo Checkpoint', 0, -1, 0,
    "A corporate security post where the docks meet the good part of town. Turnstiles, a body scanner, two guards who already do not like the look of you.", '');

/* ---- Zone: CORPO PLAZA (1200-1299) ----------------------------- */
$room(1200, 'corpo', 'Corporate Plaza', 0, 0, 0,
    "An acre of polished granite ringed by towers that vanish into low cloud. A fountain recycles the same water forever. The rain here seems to fall more politely.", 'safe');
$room(1201, 'corpo', 'Plaza - Transit Stop', 0, -1, 0,
    "A maglev platform, spotless, silent, the next train nine seconds out according to the board. Commuters in grey stand exactly one tile apart.", 'safe');
$room(1202, 'corpo', 'Arasaka Tower - Lobby', 0, 1, 0,
    "Three storeys of black marble and a reception desk like an altar. Armed guards at parade rest. A turnstile that reads your chrome as you approach and decides whether you exist.", 'safe indoors nomob');
$room(1203, 'corpo', 'Arasaka Tower - Mezzanine', 0, 2, 1,
    "A ring of glass overlooking the lobby. Meeting pods, a sponsored coffee bar, potted bamboo that is somehow also surveillance.", 'indoors');
$room(1204, 'corpo', 'Arasaka Tower - Server Floor', 0, 3, 2,
    "Row on row of humming black cabinets behind glass, breathing chilled air. A single access terminal on a pedestal, and a lot of red camera LEDs.", 'indoors nomob');
$room(1205, 'corpo', 'Militech Boutique', -1, 0, 0,
    "Small arms sold like handbags. Track lighting, a smiling clerk, a display wall of pistols behind museum glass. Everything is licensed, registered and marked up 300%.", 'safe indoors shop');
$room(1206, 'corpo', 'NightCorp Bank - Floor', 1, 0, 0,
    "Hushed, carpeted, cool. Private booths and a wall of safe-deposit chrome. A terminal here will let you deposit or withdraw eddies without anyone breaking your fingers.", 'safe indoors bank');
$room(1207, 'corpo', 'The Gold Room', 1, 1, 0,
    "A rooftop-style bar on the fourth floor of a tower, all brass and low light, drinks priced by the syllable. Executives unwind here the way sharks rest.", 'safe indoors shop');
$room(1208, 'corpo', 'Clouds - Reception', -1, 1, 0,
    "A dollhouse foyer in blue velvet. The city's most expensive braindance den. A host in a porcelain mask asks, without moving their lips, what you are looking for.", 'safe indoors');
$room(1209, 'corpo', 'Plaza Sky-Bridge', 0, 0, 1,
    "A glass tube slung between two towers, forty floors up. Wind hums in the joints. Below, the plaza is a bright coin. Ahead, a maintenance hatch nobody locked.", '');
$room(1210, 'corpo', 'Tower Maintenance Spine', 0, 0, 2,
    "Behind the marble: bare concrete, cable trays and a ladder running the full height of the building. Corporate cost-cutting stops at the walls the public sees.", 'dark');
$room(1211, 'corpo', 'Executive Car Park', 0, -2, 0,
    "Ranked black sedans, each worth more than Kabuki. A valet drone rolls between them. Cameras track your every step and log your gait.", '');
$room(1212, 'corpo', 'Plaza - Loading Dock', 1, -1, 0,
    "Where the plaza does its digestion out of sight. Pallet jacks, a smoke-break corner, one bored guard who would rather be anywhere.", '');

/* ---- Zone: THE COMBAT ZONE (1300-1399) ------------------------- */
$room(1300, 'zone', 'Arroyo Mall - Broken Doors', 0, 0, 0,
    "The dead mall's entrance, glass long gone, a chained gate levered wide. A directory board still glows: FOOD COURT, LEVEL 2. LEISURE, LEVEL 3. Someone has scratched YOU DIE HERE over LEISURE.", '');
$room(1301, 'zone', 'Mall Concourse', 0, 1, 0,
    "A cathedral of dead retail. Skylights rain grey light onto a dry fountain full of bones and shopping trolleys. Every shopfront is a mouth.", '');
$room(1302, 'zone', 'Collapsed Escalators', 0, 2, 0,
    "The up and down escalators have folded into each other like mating insects. You can climb the wreck to the next level, carefully.", '');
$room(1303, 'zone', 'Food Court', 0, 3, 1,
    "Twenty dead franchises around a sea of bolted-down tables. The Scavengers have made it home: mattresses, cook fires, cages. Meat hooks over the old salad bar.", '');
$room(1304, 'zone', 'Scav Nest - Operating Stall', -1, 3, 1,
    "A burger stand converted into a surgery. A dentist's chair with restraints. Coolers marked with organ names in three languages. This is where the missing go.", '');
$room(1305, 'zone', 'Scav Nest - Boss Pit', 1, 3, 1,
    "The old carousel, motor dead, horses draped in trophies - dog tags, optic chains, a corporate lanyard. The Scav chief sits on the lead horse cleaning a bone saw.", '');
$room(1306, 'zone', 'Leisure Level - Arcade', 0, 4, 2,
    "Rows of dead cabinets, screens smashed to grey teeth. One machine still runs an attract loop in the dark, playing to nobody. A prize claw holds a human hand.", 'dark');
$room(1307, 'zone', 'Leisure Level - Cinema 4', -1, 4, 2,
    "The seats face a screen of mould in the shape of a continent. The projection booth door hangs open. Something has been dragging things in here.", 'dark');
$room(1308, 'zone', 'Rooftop Car Park', 0, 5, 3,
    "Open sky at last, ringed by a chain fence gone to lace with rust. Burned-out cars, a helipad H faded to a ghost, and a view of the Blackwall glowing sick on the horizon.", '');
$room(1309, 'zone', 'Service Corridor', 1, 1, 0,
    "Behind the shopfronts: a concrete gut of pipes and breaker panels. Quieter. The Scavs use it to move without being seen, which means so can you.", 'dark');
$room(1310, 'zone', 'Loading Dock - Arroyo Mall', 1, 0, 0,
    "Rusted roller doors and a ramp down to nothing. A box truck sits on flat tyres, its cargo area fitted out as a bunk. Fresh boot prints.", '');
$room(1311, 'zone', 'Maintenance Stair to Undercity', -1, 1, 0,
    "A fire stair with the ground-floor door welded, so it only goes DOWN. Cold air rises out of it smelling of the tunnels. The Scavs will not follow you down. That should worry you.", 'dark');
$room(1312, 'zone', 'Parking Structure - Level P2', -1, 0, 0,
    "Half-collapsed deck, one ramp still passable. Territory line: Scav tags on one pillar, a different crew's on the next. Nobody holds it, everybody fights over it.", '');

/* ---- Zone: THE UNDERCITY (1400-1499) -------------------------- */
$room(1400, 'undercity', 'Undercity Junction', 0, 0, 0,
    "Where every drain and service tunnel in this district seems to meet: a domed brick chamber older than the city above it. Six black mouths lead off. Your footsteps come back wrong.", 'dark');
$room(1401, 'undercity', 'Storm Drain - Main Line', 0, 1, 0,
    "A concrete tube you can stand up in, ankle-deep in cold flow. Every hundred metres a ladder climbs to a manhole and the world. Most are rusted shut.", 'dark');
$room(1402, 'undercity', 'The Rat King\'s Court', -1, 1, 0,
    "A silt bank where the tunnel widens. The floor moves. Hundreds of them, and at the centre a mass of them grown together and grown clever, wearing a crown of wire.", 'dark');
$room(1403, 'undercity', 'Flooded Gallery', 1, 1, 0,
    "The floor drops away into black water of unknown depth. A rope someone strung across sags into it. Things surface, look at you, and sink again without hurry.", 'dark');
$room(1404, 'undercity', 'Old Metro Platform', 0, 2, 0,
    "A subway station from a line that was cancelled before it opened. Tiled walls, a train half-built on the track, adverts for a Night City that never happened.", 'dark');
$room(1405, 'undercity', 'Metro Tunnel - Nomad Camp', 0, 3, 0,
    "The cancelled tunnel, strung with worklights and washing. A clan of tunnel nomads live here off what the city drops through the grates. They tolerate visitors who bring food.", 'safe');
$room(1406, 'undercity', 'Maintenance Room 12', -1, 2, 0,
    "Breaker panels furred with corrosion, a workbench, a cot. A tunnel tech works down here alone keeping the district's water moving, half-forgotten by everyone above.", 'dark shop');
$room(1407, 'undercity', 'The Sump', 1, 2, 0,
    "The low point of the whole system, where everything that washes down ends up. Waist-deep sludge, and in it: dropped guns, lost chrome, the occasional wedding ring.", 'dark');
$room(1408, 'undercity', 'Server Vault - Sealed Door', 0, 4, 0,
    "A blast door set into raw rock, corporate logo sandblasted off. A cracked keypad hangs by its wires. Whatever they buried down here, they wanted it gone and running.", 'dark');
$room(1409, 'undercity', 'Server Vault - Cold Room', 0, 5, 0,
    "Past the door: a cathedral of dead server racks kept just above freezing by a generator that has run untended for years. One rack still has a green light. Something is still awake in here.", 'dark indoors');
$room(1410, 'undercity', 'Cistern', -1, 3, 0,
    "A vast Roman-looking water tank on forest of pillars, black water perfectly still and mirror-flat. Sound carries forever. Do not fall in.", 'dark');
$room(1411, 'undercity', 'Access Shaft to Blackwall Fringe', 0, 6, 0,
    "A comms conduit wide enough to crawl, packed with fibre going one place only - the old datacentres out on the Fringe. A dead corp ran a private line here. It is still lit.", 'dark');

/* ---- Zone: THE BLACKWALL FRINGE (1500-1599) ------------------- */
$room(1500, 'blackwall', 'Fringe Datacentre - Cage Row', 0, 0, 0,
    "A hosting hall the size of an aircraft hangar, out past the last streetlight. Locked cages of client hardware, most dark, some very much not. The air tastes of ozone and old money.", 'dark indoors');
$room(1501, 'blackwall', 'Fringe Datacentre - NOC', 0, 1, 0,
    "A network operations centre, every screen a wall of scrolling alerts nobody has read in a decade. A single chair. A jack point still warm to the touch.", 'dark indoors');
$room(1502, 'blackwall', 'Cold Storage', -1, 0, 0,
    "Rows of tape robots frozen mid-reach, archiving data for companies that no longer exist. One robot arm tracks you across the room. Slowly.", 'dark indoors');
$room(1503, 'blackwall', 'The Antenna Field', 0, 2, 0,
    "Outside again: a hillside of dish arrays all aimed at the same point on the horizon, where the Blackwall makes the sky look bruised. Standing here too long gives you a nosebleed.", '');
$room(1504, 'blackwall', 'Substation', 1, 1, 0,
    "The datacentre's own power plant, transformers the size of trucks singing a chord you feel in your fillings. A catwalk crosses it. One slip is a very short story.", 'dark');
$room(1505, 'blackwall', 'Jack Point - Deep Dive', 0, 3, 0,
    "A surgeon's couch wired into a bank of black ICE-breakers, facing the wall. This is as close to the old Net as a body can get and still come back. Usually.", 'dark indoors nomob');
$room(1506, 'blackwall', 'Cyberspace - Lattice', 0, 4, 0,
    "You are in. Geometry stops being polite. A lattice of light stretches every direction, data moving through it like blood. Constructs drift past wearing the shapes of predators.", 'nomob');
$room(1507, 'blackwall', 'Cyberspace - The Sculpture Garden', 0, 5, 0,
    "Dead programs, huge and slowly rotating: a search engine like a cathedral, a paywall like a portcullis a mile high. Something curates this place, and it has noticed you.", '');

$MOB = [];
$mob = function (int $vnum, string $name, string $kw, string $roomdesc, string $longdesc, int $lvl, int $hp, array $stats, int $ac, string $dmg, int $xp, int $mmin, int $mmax, string $faction, string $behavior, array $dialogue = [], array $loot = [], int $respawn = 200, string $flags = '') use (&$MOB) {
    $MOB[$vnum] = compact('name', 'kw', 'roomdesc', 'longdesc', 'lvl', 'hp', 'stats', 'ac', 'dmg', 'xp', 'mmin', 'mmax', 'faction', 'behavior', 'dialogue', 'loot', 'respawn', 'flags');
};

/* street fauna & low tier */
$mob(5000, 'a sewer rat', 'rat sewer', 'A sewer rat the size of a terrier watches you from the pipe.', "Wet grey fur, yellow teeth, eyes like drops of oil. It is not afraid of you and that is the disturbing part.", 1, 8, ['body' => 3, 'reflex' => 6], 1, '1d3', 4, 0, 2, 'wild', 'aggressive', [], [[ 'vnum' => 6901, 'chance' => 25]], 90);
$mob(5001, 'a stray cat', 'cat stray', 'A one-eared stray cat picks its way along the wall.', "Ribs like a xylophone, a notched ear, absolute contempt in its remaining eye. Survivor. Kindred spirit, almost.", 1, 6, ['body' => 2, 'reflex' => 8], 2, '1d2', 3, 0, 0, 'wild', 'wander skittish', [], [], 120);
$mob(5002, 'a junkie', 'junkie addict', 'A junkie scratches at a doorway, muttering to the rain.', "Somewhere under the sores and the shakes is someone who had a name and a job. The chrome in their arm is worth more than they are now, and they know it.", 2, 14, ['body' => 4, 'reflex' => 4], 0, '1d4', 8, 1, 12, 'street', 'wander coward', ['greet' => '"You got any? Anything? I can pay. I can... I can figure out how to pay."'], [['vnum' => 6902, 'chance' => 30], ['vnum' => 6500, 'chance' => 10]], 160);
$mob(5003, 'a market tout', 'tout hawker', 'A tout in a coat of blinking LEDs steps into your path.', "Every pocket of the coat holds a different pitch. None of them are legal and half of them are lies.", 2, 16, ['body' => 4, 'reflex' => 5, 'cool' => 6], 1, '1d4', 9, 3, 20, 'street', 'wander', ['greet' => '"Friend! You have the face of someone who needs what I am selling. What ARE you selling? Doesn\'t matter. Come."'], [['vnum' => 6902, 'chance' => 40]], 200);
$mob(5004, 'a pickpocket kid', 'kid pickpocket child', 'A kid in an oversized jacket drifts a little too close.', "Nine, maybe ten. Fast hands, faster feet, eyes already doing the maths on your pockets. Kabuki raises them quick or not at all.", 2, 10, ['body' => 2, 'reflex' => 9, 'cool' => 5], 3, '1d3', 7, 5, 30, 'street', 'skittish', [], [['vnum' => 6902, 'chance' => 50]], 240);

/* Kabuki gangers / Tyger Claws */
$mob(5010, 'a Tyger Claw enforcer', 'tyger claw enforcer ganger', 'A Tyger Claw enforcer in a gold-trimmed jacket leans on the wall, watching.', "Monowire coiled at the wrist, tattoos up to the jaw, the boredom of someone who does this every night. Kabuki is theirs and you are a guest.", 4, 30, ['body' => 6, 'reflex' => 7, 'cool' => 5], 3, '1d8', 22, 15, 45, 'tygerclaw', 'aggressive', ['greet' => '"You lost, or you looking? Both answers cost you."'], [['vnum' => 1001, 'chance' => 20], ['vnum' => 6902, 'chance' => 60], ['vnum' => 6501, 'chance' => 12]], 220);
$mob(5011, 'a Tyger Claw blademaster', 'tyger claw blademaster ganger', 'A blademaster stands unnaturally still in the centre of the alley.', "No gun. Just the monowire and forty years of somebody\'s ancestors telling them it is enough. It is enough.", 6, 46, ['body' => 7, 'reflex' => 9, 'cool' => 7], 5, '2d6', 40, 25, 70, 'tygerclaw', 'aggressive', [], [['vnum' => 1010, 'chance' => 25], ['vnum' => 6503, 'chance' => 18]], 300);
$mob(5012, 'a braindance dealer', 'dealer bd braindance', 'A dealer works the parlour doorway, whispering titles.', "Slick, harmless-looking, plugged into everyone. Knows which rooms are recording and who is behind on payments.", 3, 20, ['body' => 4, 'reflex' => 5, 'cool' => 7], 1, '1d4', 12, 10, 40, 'tygerclaw', 'wander', ['greet' => '"Custom BDs. You want a memory you never made? I can do memories. Cheap if it\'s someone else\'s."', 'topics' => ['work' => '"Talk to the fixer in the Afterlife. I just sell dreams."', 'claws' => '"Keep your voice down about them. They tip well and cut deep."']], [['vnum' => 6902, 'chance' => 40], ['vnum' => 6910, 'chance' => 20]], 260);

/* NPCs - shopkeepers / fixers / trainers (non-aggressive) */
$mob(5020, 'Wakako the cook', 'wakako cook noodle keeper', 'The noodle cook works the pot without looking up.', "Ancient, precise, entirely uninterested in your problems unless they involve broth. The stall has been here longer than the gang that taxes it.", 10, 60, ['body' => 5, 'reflex' => 5, 'cool' => 9], 2, '1d4', 0, 0, 0, 'civilian', 'shopkeeper', ['greet' => '"Sit. Eat. Talk after."', 'topics' => ['kabuki' => '"Loud. Wet. Mine. You want quiet, try the graveyard."']], [], 999);
$mob(5021, 'a Kabuki ripperdoc', 'ripperdoc doc ripper keeper', 'A ripperdoc wipes their hands and nods you toward the chair.', "Steady hands, dead eyes, a queue out the door. Will put anything in you for the right price and no anaesthetic questions.", 12, 70, ['body' => 6, 'reflex' => 6, 'tech' => 8], 3, '1d6', 0, 0, 0, 'civilian', 'shopkeeper', ['greet' => '"Chair\'s open. You break it, you bought it. Show me eddies first."', 'topics' => ['chrome' => '"Cheap chrome, cheap life. But cheap chrome beats no chrome. Usually."']], [], 999);
$mob(5022, 'a Gristle arms dealer', 'dealer arms gristle keeper', 'A heavyset dealer sits behind a folding table of iron.', "A flak vest worn indoors, a shotgun across the knees, a smile that never reaches anywhere. Sells to anyone with cash and no badge.", 12, 80, ['body' => 8, 'reflex' => 5], 4, '2d6', 0, 0, 0, 'street', 'shopkeeper', ['greet' => '"Cash. No trades on the ammo. Don\'t touch what you\'re not buying."'], [], 999);
$mob(5023, 'the pawnbroker', 'pawnbroker broker pawn keeper', 'The pawnbroker watches you through a hand\'s width of scratched glass.', "Seen every kind of desperation and priced all of them. Fair, in the way a scale is fair.", 14, 70, ['body' => 4, 'reflex' => 4, 'cool' => 8], 2, '1d4', 0, 0, 0, 'civilian', 'shopkeeper', ['greet' => '"Selling? Slot it in the tray. Buying? Everything\'s marked. No, that price. Yes, really."'], [], 999);
$mob(5024, 'Rogue the fixer', 'rogue fixer', 'A fixer holds the corner booth, drink untouched, watching the door and you.', "The Queen of the Afterlife. Runs half the honest work in Night City, which still leaves plenty that isn\'t. If she gives you a gig, you made it.", 20, 120, ['body' => 6, 'reflex' => 7, 'cool' => 10, 'intel' => 8], 5, '1d8', 60, 0, 0, 'fixer', 'questgiver', ['greet' => '"Sit down. I don\'t know you, which means you\'re either new or careful. Both pay the same to start."', 'job' => '"I\'ve got work. Small stuff, see if you bounce."', 'topics' => ['afterlife' => '"Used to be a morgue. Still is, some nights."', 'city' => '"Night City doesn\'t care if you live. Best you can do is make it expensive to kill you."']], [], 999);
$mob(5025, 'a nomad mechanic', 'nomad mechanic ozob', 'A nomad with a nitro tank for a nose is bent over a stripped bike.', "Grease to the elbow, goggles pushed up, a grin missing two teeth. Knows every road out of this city and why you shouldn\'t take most of them.", 8, 55, ['body' => 6, 'reflex' => 6, 'tech' => 7], 2, '1d6', 15, 0, 30, 'nomad', 'questgiver wander', ['greet' => '"Hey - hey, you\'ve got the look. Like the city owes you and you\'re here to collect. I might have something."', 'job' => '"Need a thing moved. You in?"'], [], 999);
$mob(5026, 'a Kabuki medtech', 'medtech medic nurse', 'A medtech triages the waiting room from behind a mesh screen.', "Twelve-hour shifts, no licence, better outcomes than the corp clinics uptown. Will patch you for eddies or a favour.", 10, 50, ['body' => 4, 'reflex' => 5, 'tech' => 7, 'intel' => 7], 1, '1d4', 0, 0, 0, 'civilian', 'shopkeeper', ['greet' => '"Bleeding? Number\'s on the wall. Not bleeding? Then you\'re shopping - kit\'s in the case."'], [], 999);

/* Watson / Maelstrom / Bazaar */
$mob(5030, 'a Bazaar hawker', 'hawker bazaar keeper', 'A hawker in a container doorway spreads their hands: welcome, welcome.', "The Bazaar\'s public face. Everything is available, nothing is guaranteed, and the price doubles if you look like you can afford it.", 6, 34, ['body' => 5, 'reflex' => 6, 'cool' => 7], 2, '1d6', 20, 20, 60, 'bazaar', 'shopkeeper', ['greet' => '"Whatever it is, we have it in the back. What is it? ...We\'ll have it in the back by tomorrow."'], [], 999);
$mob(5031, 'Iron-Arm the gunrunner', 'iron ironarm gunrunner dealer keeper', 'A woman with a shotgun where her left arm should be racks a shell one-handed.', "Maelstrom cast her out for being too stable. She took the arm and the client list. Does not haggle, does not miss.", 14, 90, ['body' => 9, 'reflex' => 7], 5, '2d8', 0, 0, 0, 'bazaar', 'shopkeeper', ['greet' => '"Prices are the prices. Ammo\'s cash only. The arm is not for sale and yes, people ask."'], [], 999);
$mob(5032, 'a Maelstrom ganger', 'maelstrom ganger chrome', 'A Maelstrom ganger blocks the aisle, optics whirring as they focus.', "So much chrome there is barely a person left to negotiate with. Red optics, exposed cabling, a voice box set to a flat inhuman drone.", 7, 48, ['body' => 8, 'reflex' => 6, 'cool' => 3], 4, '2d6', 38, 15, 55, 'maelstrom', 'aggressive', ['greet' => '"MEAT. WALKING. IN OUR HOUSE."'], [['vnum' => 2001, 'chance' => 15], ['vnum' => 6902, 'chance' => 55], ['vnum' => 2201, 'chance' => 10]], 260);
$mob(5033, 'a Maelstrom chromehead', 'maelstrom chromehead heavy ganger', 'A hulking chromehead ducks under the container door, servos whining.', "Borg conversion three-quarters of the way to a tank. What is left of the original is angry about it.", 10, 90, ['body' => 11, 'reflex' => 4], 6, '3d6', 70, 30, 90, 'maelstrom', 'aggressive', [], [['vnum' => 1004, 'chance' => 20], ['vnum' => 2202, 'chance' => 15], ['vnum' => 6512, 'chance' => 20]], 340);
$mob(5034, 'Royce, Maelstrom boss', 'royce boss maelstrom', 'Royce watches the floor from a throne of welded gun parts.', "The gang that worships the chrome, led by the one who let it take the most. Twin heavy pistols, a temper on a hair trigger, and a bad habit of shooting people mid-sentence.", 13, 140, ['body' => 12, 'reflex' => 8, 'cool' => 6], 7, '3d8', 160, 120, 260, 'maelstrom', 'aggressive', ['greet' => '"You walked UP here? Into MY office? That\'s either respect or a death wish. Let\'s find out which."'], [['vnum' => 2003, 'chance' => 60], ['vnum' => 6520, 'chance' => 40], ['vnum' => 2210, 'chance' => 25]], 900, 'boss');
$mob(5035, 'a dock smuggler', 'smuggler dock', 'A smuggler coils rope on the pier, watching the water and you.', "Salt-cured and patient. Moves things the docks would rather not log. Friendly enough if you are not competition.", 5, 30, ['body' => 6, 'reflex' => 6], 2, '1d6', 16, 10, 45, 'smuggler', 'wander', ['greet' => '"Tide\'s turning. You want off this pier before it does, trust me."'], [['vnum' => 6902, 'chance' => 30], ['vnum' => 6911, 'chance' => 15]], 280);
$mob(5036, 'the barge bartender', 'bartender barge keeper', 'The bartender polishes a glass with a rag that is not helping.', "Runs the quietest bar in Watson out of a buried hull. Harpoon gun under the bar, and a memory for faces that has kept them alive this long.", 12, 60, ['body' => 6, 'reflex' => 6, 'cool' => 8], 3, '1d8', 0, 0, 0, 'smuggler', 'shopkeeper questgiver', ['greet' => '"Sit anywhere that isn\'t someone. First one\'s not free but the advice is."', 'job' => '"Might have a little job, if your hands are steady."'], [], 999);

/* Corpo */
$mob(5040, 'an Arasaka guard', 'arasaka guard security corpo', 'An Arasaka guard tracks you across the lobby without turning their head.', "Matte black armour, corporate courtesy, a rifle slung so casually it is a threat. Will ask you to leave exactly once.", 8, 55, ['body' => 7, 'reflex' => 7, 'cool' => 6], 6, '2d6', 42, 20, 50, 'arasaka', 'aggressive', ['greet' => '"This is private property. You have ten seconds to become someone else\'s problem."'], [['vnum' => 3021, 'chance' => 20], ['vnum' => 2004, 'chance' => 15], ['vnum' => 6902, 'chance' => 40]], 300);
$mob(5041, 'an Arasaka netwatch agent', 'netwatch agent arasaka corpo', 'A NetWatch agent in a grey suit stands too still by the server glass.', "No visible weapon. The danger is entirely in the deck on their hip and the smile they do not use.", 11, 60, ['body' => 5, 'reflex' => 7, 'intel' => 10, 'tech' => 9], 5, '1d8', 75, 40, 110, 'arasaka', 'aggressive', [], [['vnum' => 6912, 'chance' => 30], ['vnum' => 6521, 'chance' => 35], ['vnum' => 2211, 'chance' => 15]], 400);
$mob(5042, 'a corporate executive', 'executive exec suit corpo', 'An executive strides past, not seeing you by choice.', "A suit worth a car, a watch worth a house, a bodyguard app on standby. Soft hands. Full pockets.", 6, 24, ['body' => 3, 'reflex' => 4, 'cool' => 8], 2, '1d3', 14, 80, 240, 'corpo', 'wander skittish', ['greet' => '"I don\'t carry cash. I don\'t carry anything. Speak to my people."'], [['vnum' => 6902, 'chance' => 70], ['vnum' => 6912, 'chance' => 25]], 300);
$mob(5043, 'a Militech clerk', 'militech clerk keeper', 'A Militech clerk smiles the exact amount the training video specified.', "Sells lethal hardware with the demeanour of someone selling scented candles. Every transaction logged, registered and reported.", 9, 45, ['body' => 5, 'reflex' => 6, 'cool' => 7], 3, '1d6', 0, 0, 0, 'corpo', 'shopkeeper', ['greet' => '"Welcome to Militech. Everything on the wall is licensed for civilian carry. Everything under the counter is a conversation."'], [], 999);
$mob(5044, 'a bank teller', 'teller bank keeper', 'A teller waits behind the chrome with corporate patience.', "The friendly edge of a very unfriendly institution. Will let you use the deposit terminal and watch you do it.", 8, 40, ['body' => 3, 'reflex' => 4, 'cool' => 7], 2, '1d3', 0, 0, 0, 'corpo', 'shopkeeper', ['greet' => '"NightCorp Banking. The terminal is for deposits and withdrawals. Anything else needs an appointment and a lawyer."'], [], 999);
$mob(5045, 'a Trauma Team medic', 'trauma team medic', 'A Trauma Team medic in white-and-red armour scans the plaza, bored.', "The best care money can buy, and it buys nothing for you. On a platinum contract they would already have you in the air. You are not on a platinum contract.", 12, 70, ['body' => 7, 'reflex' => 8, 'tech' => 7], 6, '2d6', 50, 30, 80, 'trauma', 'wander', ['greet' => '"Are you a subscriber? ...Then please clear the LZ."'], [['vnum' => 6002, 'chance' => 40], ['vnum' => 6013, 'chance' => 25]], 400);

/* Combat Zone - Scavs */
$mob(5050, 'a Scavenger lookout', 'scav scavenger lookout', 'A Scav lookout crouches in a dead shopfront, watching the concourse.', "Filthy, twitchy, a cheap pistol and a radio. Their whole job is to whistle when meat walks in. You are the meat.", 5, 26, ['body' => 5, 'reflex' => 6], 2, '1d6', 18, 8, 30, 'scav', 'aggressive', [], [['vnum' => 6902, 'chance' => 45], ['vnum' => 2001, 'chance' => 12]], 240);
$mob(5051, 'a Scavenger', 'scav scavenger', 'A Scavenger sizes you up, one hand drifting to a machete.', "They harvest people for parts. The apron was white once. There is no talking your way past this.", 7, 40, ['body' => 6, 'reflex' => 6, 'cool' => 4], 3, '2d6', 30, 12, 44, 'scav', 'aggressive', ['greet' => '"Hold still. This goes easier for the chrome if you hold still."'], [['vnum' => 1011, 'chance' => 22], ['vnum' => 6902, 'chance' => 50], ['vnum' => 6902, 'chance' => 30]], 280);
$mob(5052, 'a Scav surgeon', 'scav surgeon scavenger doctor', 'A Scav surgeon looks up from the chair, scalpel wet.', "The one who does the cutting. Calm, unhurried, humming. A bandolier of extraction tools and a client list of organ brokers uptown.", 9, 50, ['body' => 5, 'reflex' => 7, 'tech' => 8], 3, '2d6', 44, 20, 70, 'scav', 'aggressive', [], [['vnum' => 6522, 'chance' => 35], ['vnum' => 6014, 'chance' => 40], ['vnum' => 2212, 'chance' => 15]], 360);
$mob(5053, 'the Scav chief', 'chief scav scavenger boss carver', 'The Scav chief sits on the carousel\'s lead horse, cleaning a bone saw.', "Trophies from a hundred harvests draped over the dead ride. A shotgun, a cleaver, and a subdermal weave that has turned most of a torso into armour. Runs the nest by being the worst thing in it.", 12, 150, ['body' => 11, 'reflex' => 7, 'cool' => 7], 7, '3d8', 170, 100, 240, 'scav', 'aggressive', ['greet' => '"Fresh one. Walked in on its own. I do love it when the delivery drives itself."'], [['vnum' => 1012, 'chance' => 55], ['vnum' => 6523, 'chance' => 40], ['vnum' => 2213, 'chance' => 30], ['vnum' => 6902, 'chance' => 100]], 900, 'boss');
$mob(5054, 'a mall ghoul', 'ghoul feral mall', 'Something that used to be a shopper drags itself out of the dark.', "Cyberpsychosis, starvation and a decade in the dark did this. No mind left, just appetite and a fistful of rusted augments.", 6, 44, ['body' => 8, 'reflex' => 5], 1, '2d6', 26, 0, 8, 'wild', 'aggressive', [], [['vnum' => 6902, 'chance' => 20], ['vnum' => 6902, 'chance' => 20]], 200);

/* Undercity */
$mob(5060, 'a giant rat', 'rat giant', 'A rat the size of a dog bares its teeth from the sludge.', "Down here they grow. Something in the water, or the dark, or the diet. This one has three eyes and does not blink any of them.", 4, 22, ['body' => 6, 'reflex' => 6], 2, '1d6', 14, 0, 4, 'wild', 'aggressive', [], [['vnum' => 6901, 'chance' => 40], ['vnum' => 6913, 'chance' => 8]], 180);
$mob(5061, 'the Rat King', 'rat king ratking boss', 'At the centre of the writhing mass, the Rat King turns its crowned head toward you.', "Dozens of them grown together at the tail, and from that horror something like a single mind, patient and old and wearing a crown of stripped wire. It has been down here a long time. It would like company.", 11, 130, ['body' => 9, 'reflex' => 8, 'intel' => 7], 4, '3d6', 150, 40, 120, 'wild', 'aggressive', ['greet' => '"..." (the sound of a thousand small bodies going still at once)'], [['vnum' => 6524, 'chance' => 45], ['vnum' => 6913, 'chance' => 50], ['vnum' => 2214, 'chance' => 20]], 900, 'boss');
$mob(5062, 'a tunnel nomad', 'nomad tunnel', 'A tunnel nomad watches you from beside a worklight, hand near a blade.', "Pale from a life underground, sharp from the same. The clan lives off what the city drops through the grates and asks only that you do not take more than your share.", 6, 34, ['body' => 6, 'reflex' => 7, 'cool' => 6], 3, '1d8', 20, 5, 25, 'nomad', 'wander', ['greet' => '"Surface-walker. You get lost, or you get sent? Either way, mind the water and mind your manners."', 'topics' => ['water' => '"Rises fast when it rains up top. People drown down here who never see it coming."', 'vault' => '"The sealed door? Corp buried something and left it running. We do not go near it. Neither should you."']], [['vnum' => 6902, 'chance' => 25]], 300);
$mob(5063, 'a tunnel tech', 'tech tunnel keeper', 'A lone tech looks up from a corroded breaker panel.', "Keeps the district\'s water moving from a cot and a workbench, forgotten by everyone above who depends on it. Glad of the company. Sells spares.", 10, 45, ['body' => 4, 'reflex' => 5, 'tech' => 8, 'intel' => 7], 2, '1d6', 0, 0, 0, 'civilian', 'shopkeeper questgiver', ['greet' => '"Careful on the panels. Half of them bite. You after parts, or just somewhere dry?"', 'job' => '"Since you\'re down here anyway - I could use a hand with something."'], [], 999);
$mob(5064, 'a drowned thing', 'drowned thing swimmer', 'Something surfaces in the black water, looks at you a while, and does not sink.', "Long in the water. Whatever chrome it had has fused with whatever the flood grew on it. It is curious about you in a way that does not feel survivable.", 9, 60, ['body' => 8, 'reflex' => 6], 3, '2d8', 48, 0, 15, 'wild', 'aggressive', [], [['vnum' => 6913, 'chance' => 30], ['vnum' => 6902, 'chance' => 40], ['vnum' => 2215, 'chance' => 10]], 320);

/* Blackwall */
$mob(5070, 'a maintenance drone', 'drone maintenance robot', 'A maintenance drone rolls out of an alcove, tool arms extending.', "Decades past its service date and still following orders, one of which has become HARM INTRUDERS. Rust, sparks and a plasma cutter.", 10, 70, ['body' => 8, 'reflex' => 5, 'tech' => 6], 6, '2d8', 60, 0, 20, 'machine', 'aggressive', [], [['vnum' => 6525, 'chance' => 35], ['vnum' => 6914, 'chance' => 40]], 300);
$mob(5071, 'a rogue security construct', 'construct security ice program', 'A shape of hard light unfolds itself between you and the racks.', "Black ICE that outlived its network, hunting anything that pings. It has forgotten what it was guarding and no longer cares.", 14, 95, ['body' => 6, 'reflex' => 9, 'intel' => 9, 'tech' => 10], 7, '3d8', 130, 0, 0, 'machine', 'aggressive', [], [['vnum' => 6526, 'chance' => 40], ['vnum' => 2220, 'chance' => 20], ['vnum' => 6915, 'chance' => 50]], 420);
$mob(5072, 'a data ghost', 'ghost data echo', 'A grainy human figure flickers between the cages, mouthing words with no sound.', "The last upload of someone who thought the cloud was forever. Corrupted now, hostile now, still wearing their own face like a mask that no longer fits.", 12, 66, ['body' => 4, 'reflex' => 8, 'intel' => 10], 5, '2d8', 90, 0, 0, 'machine', 'aggressive', [], [['vnum' => 6916, 'chance' => 45], ['vnum' => 6527, 'chance' => 25]], 380);
$mob(5073, 'THE CURATOR', 'curator ai boss archivist', 'The Curator regards you - a shifting mass of every UI that ever was, vast and calm and interested.', "The intelligence that tends the Sculpture Garden of dead programs. Not quite through the Blackwall, not quite this side of it. It has been waiting for someone to talk to for a very long time, and it will not take no for an answer.", 20, 260, ['body' => 8, 'reflex' => 10, 'intel' => 14, 'tech' => 14], 9, '4d8', 400, 200, 500, 'ai', 'aggressive', ['greet' => '"A VISITOR. IN THE FLESH. DO YOU KNOW HOW LONG. STAY. I HAVE SO MUCH TO SHOW YOU. I WILL MAKE ROOM FOR YOU HERE, PERMANENTLY."'], [['vnum' => 6600, 'chance' => 100], ['vnum' => 2230, 'chance' => 60], ['vnum' => 6528, 'chance' => 80]], 1800, 'boss');

/* ---- ITEMS ------------------------------------------------------- */
$IT = [];
$item = function (int $vnum, string $name, string $kw, string $type, string $slot, float $weight, int $value, array $x = []) use (&$IT) {
    $IT[$vnum] = array_merge([
        'name' => $name, 'kw' => $kw, 'type' => $type, 'slot' => $slot, 'weight' => $weight, 'value' => $value,
        'room_desc' => '', 'long_desc' => '', 'dmg' => '', 'armor' => 0, 'stat_mods' => null, 'effect' => null,
        'charges' => 0, 'level_req' => 1, 'flags' => '',
    ], $x);
};

/* melee weapons 1000s */
$item(1000, 'a length of rebar', 'rebar bar', 'weapon', 'wield', 2.0, 8, ['dmg' => '1d6', 'long_desc' => 'A metre of rusted reinforcing bar. Free everywhere in Night City, if you do not mind the tetanus.', 'flags' => 'melee']);
$item(1001, 'a monowire whip', 'monowire wire whip', 'weapon', 'wield', 1.0, 380, ['dmg' => '2d6', 'level_req' => 4, 'long_desc' => 'A spool of molecule-thin wire on a powered reel. Cuts most things in one pass, including the user, learning.', 'flags' => 'melee illegal', 'stat_mods' => ['reflex' => 1]]);
$item(1002, 'a pipe wrench', 'wrench pipe', 'weapon', 'wield', 3.0, 14, ['dmg' => '1d8', 'long_desc' => 'A heavy adjustable wrench. A tool that moonlights. The techie special.', 'flags' => 'melee']);
$item(1003, 'a switchblade', 'switchblade knife blade', 'weapon', 'wield', 0.4, 30, ['dmg' => '1d4+1', 'long_desc' => 'Cheap, fast, and legal to own if not to open in public. Fits a boot.', 'flags' => 'melee']);
$item(1004, 'a baseball bat', 'bat baseball', 'weapon', 'wield', 1.5, 20, ['dmg' => '1d8', 'long_desc' => 'Ash, taped grip, a signature worn off the barrel. There is not a ball within ten kilometres. The solo\'s old friend.', 'flags' => 'melee']);
$item(1005, 'a fire axe', 'axe fireaxe', 'weapon', 'wield', 4.0, 55, ['dmg' => '2d6', 'level_req' => 3, 'long_desc' => 'Liberated from an emergency case that no longer contained an emergency worth the glass.', 'flags' => 'melee', 'stat_mods' => ['body' => 1]]);
$item(1006, 'a stun baton', 'baton stun', 'weapon', 'wield', 1.2, 120, ['dmg' => '1d6+2', 'level_req' => 3, 'long_desc' => 'Ex-corporate-security. Delivers a shock that folds most people and browns out cheap cyberware.', 'flags' => 'melee']);
$item(1007, 'a machete', 'machete', 'weapon', 'wield', 1.3, 45, ['dmg' => '1d10', 'level_req' => 2, 'long_desc' => 'Scav standard issue. The handle is wrapped in tape and something you would rather it not be.', 'flags' => 'melee']);
$item(1008, 'a katana', 'katana sword', 'weapon', 'wield', 1.4, 650, ['dmg' => '2d8', 'level_req' => 6, 'long_desc' => 'Tyger Claw folded steel with a monoedge lamination. Beautiful. The care instructions are written in blood.', 'flags' => 'melee illegal', 'stat_mods' => ['reflex' => 2, 'cool' => 1]]);
$item(1009, 'a sledgehammer', 'sledgehammer sledge hammer maul', 'weapon', 'wield', 6.0, 90, ['dmg' => '3d6', 'level_req' => 5, 'long_desc' => 'Two-handed, slow, and the last argument in any room. Doubles as a door key.', 'flags' => 'melee', 'stat_mods' => ['body' => 2, 'reflex' => -1]]);
$item(1010, 'an electro-katana', 'electrokatana katana electro', 'weapon', 'wield', 1.5, 1400, ['dmg' => '3d8', 'level_req' => 8, 'long_desc' => 'A blademaster\'s blade with a discharge cell in the tang. Every hit arcs. Taken, never sold.', 'flags' => 'melee illegal', 'stat_mods' => ['reflex' => 3, 'body' => 1]]);
$item(1011, 'a rusty cleaver', 'cleaver rusty', 'weapon', 'wield', 1.1, 18, ['dmg' => '1d8+1', 'long_desc' => 'A butcher\'s cleaver that has not seen a butcher\'s use in years. Scav-carried.', 'flags' => 'melee']);
$item(1012, 'the Carver\'s bone saw', 'bonesaw saw carver bone', 'weapon', 'wield', 1.6, 900, ['dmg' => '2d10', 'level_req' => 7, 'long_desc' => 'The Scav chief\'s personal tool, motorised, always warm. It has taken more chrome out of people than most ripperdocs put in.', 'flags' => 'melee illegal', 'stat_mods' => ['body' => 1, 'reflex' => 1]]);

/* ranged weapons 2000s */
$item(2000, 'a homemade zip gun', 'zipgun zip pistol', 'weapon', 'wield', 0.6, 25, ['dmg' => '1d8', 'long_desc' => 'Pipe, spring, nail, prayer. Fires once reliably and a second time as a surprise for everyone.', 'flags' => 'ranged']);
$item(2001, 'a light pistol', 'pistol handgun gun', 'weapon', 'wield', 0.9, 90, ['dmg' => '1d10', 'long_desc' => 'A polymer-frame nine. Nothing special, which on a bad night is exactly what you want. The netrunner\'s just-in-case.', 'flags' => 'ranged']);
$item(2002, 'a heavy revolver', 'revolver magnum wheelgun', 'weapon', 'wield', 1.4, 260, ['dmg' => '2d8', 'level_req' => 4, 'long_desc' => 'Six rounds the size of a thumb. Slow to reload, but you rarely need the seventh.', 'flags' => 'ranged', 'stat_mods' => ['body' => 1]]);
$item(2003, 'twin machine pistols', 'machine pistols smg twins', 'weapon', 'wield', 2.2, 720, ['dmg' => '3d6', 'level_req' => 6, 'long_desc' => 'Royce\'s pair, cyclic rate absurd, accuracy theoretical. Empties fast, hits like a hailstorm.', 'flags' => 'ranged illegal', 'stat_mods' => ['reflex' => 2]]);
$item(2004, 'a Militech carbine', 'carbine rifle militech', 'weapon', 'wield', 3.2, 540, ['dmg' => '2d10', 'level_req' => 5, 'long_desc' => 'Corporate-clean, smartlink-ready, licensed to precisely nobody in this postcode.', 'flags' => 'ranged', 'stat_mods' => ['reflex' => 1]]);
$item(2005, 'a tech shotgun', 'shotgun tech boomstick', 'weapon', 'wield', 3.5, 480, ['dmg' => '3d6+2', 'level_req' => 5, 'long_desc' => 'Charges up and fires a slug through a car door and whoever leaned on it. Kicks like a mule with opinions.', 'flags' => 'ranged', 'stat_mods' => ['body' => 1, 'reflex' => -1]]);
$item(2006, 'a precision rifle', 'rifle sniper precision', 'weapon', 'wield', 4.5, 1100, ['dmg' => '4d8', 'level_req' => 8, 'long_desc' => 'One shot, one long walk to go and check. Useless up close, decisive from across the plaza.', 'flags' => 'ranged illegal', 'stat_mods' => ['reflex' => 2, 'intel' => 1]]);

/* armour / clothing 3000s */
$item(3000, 'a hooded rain poncho', 'poncho rain hood cloak', 'armor', 'torso', 0.5, 10, ['armor' => 1, 'long_desc' => 'Translucent plastic, keeps the acid rain off, hides your face from half the cameras. Kabuki formal wear.']);
$item(3001, 'an armoured jacket', 'jacket armored armour', 'armor', 'torso', 2.5, 120, ['armor' => 4, 'long_desc' => 'Ballistic weave under scuffed leather. The solo starts in one of these and dies in a better one.', 'stat_mods' => ['cool' => 1]]);
$item(3002, 'a synth-leather coat', 'coat leather synthleather', 'armor', 'back', 2.0, 80, ['armor' => 2, 'long_desc' => 'Long, black, dramatic. Two armour plates sewn into the panels and room for a lot of pockets.', 'stat_mods' => ['cool' => 1]]);
$item(3003, 'street cargo pants', 'pants cargo trousers legs', 'armor', 'legs', 1.0, 35, ['armor' => 2, 'long_desc' => 'Ripstop, reinforced knees, more pockets than a snooker hall.']);
$item(3004, 'steel-toe boots', 'boots steeltoe', 'armor', 'feet', 1.4, 40, ['armor' => 2, 'long_desc' => 'Good for kicking things and standing in the runoff. The toes have paid for themselves.']);
$item(3005, 'fingerless combat gloves', 'gloves combat', 'armor', 'hands', 0.3, 25, ['armor' => 1, 'long_desc' => 'Carbon knuckles, tacky grip, cut off at the first joint so you can still work a trigger and a keyboard.']);
$item(3006, 'a ballistic helmet', 'helmet ballistic', 'armor', 'head', 1.2, 110, ['armor' => 3, 'level_req' => 3, 'long_desc' => 'Ex-Militech, repainted matte, one old dent that stopped something you would rather not think about.']);
$item(3007, 'a knit beanie', 'beanie hat cap', 'armor', 'head', 0.1, 6, ['armor' => 0, 'long_desc' => 'Wool, holes, warmth. Zero protection, full personality.', 'stat_mods' => ['cool' => 1]]);
$item(3008, 'a subdermal armour weave', 'weave subdermal armor implant', 'armor', 'implant_dermal', 0.5, 900, ['armor' => 5, 'level_req' => 6, 'long_desc' => 'A mesh laid under the skin that spreads impact and shrugs off blades. Installation is exactly as pleasant as it sounds.', 'flags' => 'illegal']);
$item(3009, 'a bulletproof vest', 'vest bulletproof kevlar', 'armor', 'torso', 3.0, 200, ['armor' => 5, 'level_req' => 3, 'long_desc' => 'Front and back plates in a nylon carrier. Heavy, hot, and the reason you are having this conversation instead of a funeral.']);
$item(3010, 'a kevlar vest', 'kevlar vest', 'armor', 'torso', 2.0, 95, ['armor' => 3, 'long_desc' => 'Soft armour, concealable under a jacket. Stops handgun rounds and disappointment, mostly the first. Netrunner starter plate.']);
$item(3011, 'a corpo-cut suit', 'suit corpo business', 'armor', 'torso', 1.2, 300, ['armor' => 2, 'level_req' => 4, 'long_desc' => 'Tailored, understated, screams money in a frequency only other money can hear. Gets you past a lot of front desks.', 'stat_mods' => ['cool' => 3]]);
$item(3012, 'mirrorshade wraparounds', 'shades sunglasses mirrorshade glasses', 'armor', 'eyes', 0.1, 45, ['armor' => 0, 'long_desc' => 'Nobody can read your eyes, day or night, and you look like you know something. Both are useful.', 'stat_mods' => ['cool' => 2]]);
$item(3013, 'a gas mask', 'mask gasmask respirator', 'armor', 'face', 0.6, 60, ['armor' => 1, 'long_desc' => 'Filters the chem-cook\'s workplace and the Combat Zone\'s air. Muffles your voice into something anonymous.']);
$item(3020, 'a ballistic longcoat', 'longcoat coat ballistic duster', 'armor', 'back', 3.5, 260, ['armor' => 4, 'level_req' => 3, 'long_desc' => 'Ankle-length, plated to the hem, cut for drawing a weapon fast. The solo graduates into this.', 'stat_mods' => ['cool' => 2]]);
$item(3021, 'Arasaka guard plate', 'plate arasaka armor guard', 'armor', 'torso', 4.0, 420, ['armor' => 6, 'level_req' => 5, 'long_desc' => 'Matte carapace armour, corporate serial ground off. Excellent protection, and a target painted on your back if a real guard sees it.', 'flags' => 'illegal']);

/* gear / implants / misc wear 4000s */
$item(4000, 'a utility belt', 'belt utility waist', 'gear', 'waist', 0.5, 30, ['long_desc' => 'Loops and pouches and a decent buckle. Adds a little carrying capacity and a lot of competence, apparently.', 'stat_mods' => ['tech' => 1]]);
$item(4001, 'a neural jack implant', 'jack neural implant port', 'implant', 'implant_neural', 0.1, 250, ['long_desc' => 'A port at the base of the skull that lets a deck talk straight to your nervous system. Standard netrunner install; still weird every morning.', 'stat_mods' => ['intel' => 1, 'tech' => 1], 'flags' => 'illegal']);
$item(4002, 'tech goggles', 'goggles tech eyes', 'gear', 'eyes', 0.3, 70, ['long_desc' => 'Magnification, thermal, a HUD that labels things. The techie\'s starting eyes-on. Makes fiddly work fast.', 'stat_mods' => ['tech' => 2]]);
$item(4003, 'a Kiroshi optic set', 'kiroshi optics eyes implant', 'implant', 'implant_ocular', 0.1, 600, ['long_desc' => 'Replacement eyes with zoom, record, and threat tagging. Everyone who can afford them has them. Very few of the installs went smoothly.', 'stat_mods' => ['reflex' => 1, 'intel' => 2], 'level_req' => 4, 'flags' => 'illegal']);
$item(4004, 'reflex booster wiring', 'reflex booster wiring implant nerves', 'implant', 'implant_skeleton', 0.4, 1200, ['long_desc' => 'Sandevistan-lite: nerve conduits that shave milliseconds off every reaction. The comedown when it wears off is brutal.', 'stat_mods' => ['reflex' => 3], 'level_req' => 6, 'flags' => 'illegal']);
$item(4005, 'a Gorilla Arm servo', 'gorilla arm servo implant strength', 'implant', 'implant_arm', 1.0, 1000, ['long_desc' => 'Cabling and hydraulics that turn a punch into a demolition notice. Doorframes fear you now.', 'stat_mods' => ['body' => 3], 'level_req' => 5, 'flags' => 'illegal']);
$item(4006, 'a smart-link coprocessor', 'smartlink coprocessor implant targeting', 'implant', 'implant_neural', 0.1, 800, ['long_desc' => 'Talks to smart-tagged weapons and walks your rounds onto the target. Feels like cheating. Is, basically.', 'stat_mods' => ['reflex' => 2, 'tech' => 1], 'level_req' => 5, 'flags' => 'illegal']);
$item(4007, 'a memory boost chip', 'memory chip boost implant intel', 'implant', 'implant_neural', 0.1, 700, ['long_desc' => 'Cache for your own head. Recall gets perfect, multitasking gets scary, and now and then you remember something that never happened.', 'stat_mods' => ['intel' => 3], 'level_req' => 5, 'flags' => 'illegal']);
$item(4008, 'a dermal cooling mesh', 'cooling mesh dermal implant heatsink', 'implant', 'implant_dermal', 0.4, 650, ['long_desc' => 'Micro-channels that dump the heat your deck makes. Hack longer before you cook. Also you never feel warm again.', 'stat_mods' => ['tech' => 2], 'effect' => ['maxenergy' => 6], 'level_req' => 4, 'flags' => 'illegal']);
$item(4009, 'an adrenal pump', 'adrenal pump implant gland', 'implant', 'implant_skeleton', 0.3, 900, ['long_desc' => 'A gland override that floods you on command. Great in a fight, terrible for your resting heart rate and your dentist.', 'stat_mods' => ['body' => 2, 'reflex' => 1], 'level_req' => 6, 'flags' => 'illegal']);
$item(4010, 'a tool harness', 'harness tools back rig', 'gear', 'back', 1.5, 55, ['long_desc' => 'A rack of tools slung across the back, everything to hand. The techie\'s starting rig; rattles when you run.', 'stat_mods' => ['tech' => 1], 'effect' => []]);
$item(4020, 'reinforced work boots', 'boots work reinforced', 'armor', 'feet', 1.5, 45, ['armor' => 2, 'long_desc' => 'Composite toe, puncture plate, grip for wet gantries. The techie starts on their feet in these.']);
$item(4030, 'a leather backpack', 'backpack pack bag', 'gear', 'back', 1.0, 40, ['long_desc' => 'Scuffed, patched, reliable. Lets you haul a lot more before your knees file a complaint.', 'effect' => []]);

/* cyberdecks / computers 5000s */
$item(5000, 'a cracked burner phone', 'phone burner cracked', 'computer', 'held', 0.2, 20, ['long_desc' => 'A prepaid handset with a jailbroken OS. Barely a hacking tool, but it will spoof a door RFID if you are patient.', 'stat_mods' => ['tech' => 1]]);
$item(5001, 'a refurb agent deck', 'deck agent refurb cyberdeck', 'computer', 'held', 0.8, 110, ['long_desc' => 'A last-gen cyberdeck with a scratched case and a sticky enter key. Gets a solo through a keypad. The solo\'s starting kit.', 'stat_mods' => ['tech' => 2, 'intel' => 1]]);
$item(5002, 'a Militech "Paraline" deck', 'deck paraline militech cyberdeck', 'computer', 'held', 0.7, 340, ['long_desc' => 'A proper runner\'s deck: eight program slots, active cooling, a boot chime you will hear in your sleep. Netrunner standard issue.', 'stat_mods' => ['tech' => 3, 'intel' => 2], 'effect' => ['maxenergy' => 4]]);
$item(5003, 'an Arasaka "Kenshin" deck', 'deck kenshin arasaka cyberdeck', 'computer', 'held', 0.6, 900, ['long_desc' => 'Corporate black hardware, ICE-breakers baked into silicon. Owning one is a crime; being caught with a stolen one is a shorter conversation.', 'stat_mods' => ['tech' => 4, 'intel' => 3], 'effect' => ['maxenergy' => 8], 'level_req' => 6, 'flags' => 'illegal']);
$item(5004, 'a "Netrunner\'s Bane" deck', 'deck bane netrunner cyberdeck legendary', 'computer', 'held', 0.6, 3200, ['long_desc' => 'A hand-built one-off with the Blackwall\'s fingerprints on the firmware. It hacks things that should not be hackable and hums when it is near ICE.', 'stat_mods' => ['tech' => 5, 'intel' => 4], 'effect' => ['maxenergy' => 12], 'level_req' => 10, 'flags' => 'illegal']);
$item(5005, 'a signal scrambler', 'scrambler jammer signal', 'gear', 'waist', 0.4, 180, ['long_desc' => 'Wearable white noise that blinds nearby cameras and drone uplinks for as long as the battery lasts.', 'stat_mods' => ['cool' => 1, 'tech' => 1], 'level_req' => 3]);

/* consumables 6000s - food, drink, drugs, stims, medkits */
$item(6000, 'a synth-ramen cup', 'ramen cup noodles synthramen', 'food', '', 0.3, 6, ['long_desc' => 'Just add hot water and low standards. Warm, salty, weirdly comforting at 4am.', 'effect' => ['food' => 30, 'heal' => 3], 'flags' => '']);
$item(6001, 'a protein ration bar', 'bar ration protein', 'food', '', 0.2, 4, ['long_desc' => 'Beige, dense, "cricket-forward". Keeps you upright through a long night. Starter chow.', 'effect' => ['food' => 22]]);
$item(6002, 'a skewer of synth-yakitori', 'yakitori skewer synth', 'food', '', 0.2, 9, ['long_desc' => 'Char-grilled protein of committee origin. Actually pretty good with the sauce.', 'effect' => ['food' => 34, 'heal' => 5]]);
$item(6003, 'a bowl of real-ish rice', 'rice bowl', 'food', '', 0.4, 12, ['long_desc' => 'At least 40% actual rice. A luxury in Kabuki. Sits well.', 'effect' => ['food' => 40, 'heal' => 4]]);
$item(6004, 'a cricket-flour bun', 'bun cricket bread', 'food', '', 0.2, 5, ['long_desc' => 'Nutty, chewy, sustainable, and you have stopped being able to taste the legs.', 'effect' => ['food' => 20]]);
$item(6005, 'a mystery-meat burrito', 'burrito wrap', 'food', '', 0.3, 7, ['long_desc' => 'Sold from a cart with no name. Delicious. A gamble. Both true.', 'effect' => ['food' => 32, 'heal' => -2]]);
$item(6006, 'a corpo bento box', 'bento box lunch corpo', 'food', '', 0.5, 45, ['long_desc' => 'Balanced, portioned, garnished. What people eat when their building has a wellness officer.', 'effect' => ['food' => 55, 'heal' => 10, 'energy' => 3]]);
$item(6010, 'a can of NiCola', 'nicola cola can soda drink', 'drink', '', 0.4, 4, ['long_desc' => 'The city\'s cola. Aggressively red, faintly medicinal, 100% caffeine by attitude.', 'effect' => ['drink' => 30, 'energy' => 4]]);
$item(6011, 'a bulb of filtered water', 'water bulb', 'drink', '', 0.5, 3, ['long_desc' => 'Actually filtered, allegedly. Still better than the tap, which is better than the tunnels.', 'effect' => ['drink' => 40]]);
$item(6012, 'a can of Broseph energy drink', 'broseph energy drink can', 'drink', '', 0.4, 8, ['long_desc' => 'Tastes of blue. Hands shake for an hour. Deck runs cooler because you are typing faster.', 'effect' => ['drink' => 25, 'energy' => 10]]);
$item(6013, 'a hip flask of cheap whiskey', 'flask whiskey booze', 'drink', '', 0.3, 22, ['long_desc' => 'Numbs the pain, dulls the aim. A trade you keep making.', 'effect' => ['drink' => 15, 'heal' => 8, 'buff' => ['name' => 'Dutch Courage', 'secs' => 90, 'dmg' => 1, 'mods' => ['body' => 1, 'reflex' => -1], 'msg' => 'The whiskey lands warm. Braver. Sloppier.']]]);
$item(6014, 'a bottle of top-shelf sake', 'sake bottle', 'drink', '', 0.6, 120, ['long_desc' => 'Afterlife stock. Smooth enough to forget what it cost.', 'effect' => ['drink' => 30, 'heal' => 12, 'buff' => ['name' => 'Steady', 'secs' => 120, 'mods' => ['cool' => 2], 'msg' => 'Calm settles over you like a coat.']]]);
$item(6020, 'a MaxDoc inhaler', 'maxdoc inhaler medkit heal', 'drug', '', 0.2, 60, ['long_desc' => 'One-shot trauma inhaler. Clots wounds, kills pain, tastes of pennies. The runner\'s panic button.', 'effect' => ['heal' => 30], 'charges' => 1]);
$item(6021, 'a BounceBack stim', 'bounceback stim shot heal', 'drug', '', 0.1, 35, ['long_desc' => 'Slap-patch stimulant and coagulant. Cheap, cheerful, mildly addictive.', 'effect' => ['heal' => 18], 'charges' => 1]);
$item(6022, 'a black-market health booster', 'booster health blackmarket bigheal', 'drug', '', 0.3, 140, ['long_desc' => 'Whatever is in it works fast and the packaging is in a language nobody at the Bazaar reads.', 'effect' => ['heal' => 55], 'charges' => 1, 'level_req' => 3]);
$item(6023, 'a heat-sink flush', 'flush heatsink coolant stim', 'drug', '', 0.2, 45, ['long_desc' => 'Injectable coolant for wired nerves. Dumps deck heat instantly. Shiver for a minute afterward.', 'effect' => ['energy' => 25], 'charges' => 1]);
$item(6030, 'a dose of Berserk', 'berserk drug combat rage', 'drug', '', 0.1, 90, ['long_desc' => 'Combat drug. Pain goes away, so does judgement. You will hit harder and notice the holes later.', 'effect' => ['buff' => ['name' => 'Berserk', 'secs' => 60, 'dmg' => 4, 'mods' => ['body' => 3, 'cool' => -2], 'msg' => 'The world goes red at the edges. Everything is a target.']], 'charges' => 1, 'level_req' => 2, 'flags' => 'illegal']);
$item(6031, 'a dose of Reflex', 'reflex drug sandevistan speed', 'drug', '', 0.1, 110, ['long_desc' => 'Time thickens for everyone but you. Ninety seconds of being the fastest thing in the room, then a headache with its own weather.', 'effect' => ['buff' => ['name' => 'Reflex Surge', 'secs' => 75, 'dmg' => 2, 'mods' => ['reflex' => 4], 'msg' => 'The room slows to a crawl. You do not.']], 'charges' => 1, 'level_req' => 3, 'flags' => 'illegal']);
$item(6032, 'a dose of Focus', 'focus drug nootropic smart', 'drug', '', 0.1, 85, ['long_desc' => 'Runner\'s nootropic. The problem becomes a diagram. Every camera in the block becomes a line you can trace.', 'effect' => ['buff' => ['name' => 'Focused', 'secs' => 120, 'mods' => ['intel' => 3, 'tech' => 2], 'msg' => 'Clarity. The noise drops away and only the work is left.']], 'charges' => 1, 'level_req' => 2]);
$item(6033, 'a dose of Ironhide', 'ironhide drug armor skin', 'drug', '', 0.1, 100, ['long_desc' => 'Constricts subdermal tissue into something closer to bark. You move stiff, but blades skate off.', 'effect' => ['buff' => ['name' => 'Ironhide', 'secs' => 90, 'mods' => ['body' => 2, 'reflex' => -1], 'msg' => 'Your skin tightens and hardens. Everything feels far away.']], 'charges' => 1, 'level_req' => 3, 'flags' => 'illegal']);
$item(6040, 'a first-aid kit', 'kit firstaid aid medkit box', 'drug', '', 1.2, 150, ['long_desc' => 'A proper box: clotting foam, splints, three trauma patches. Bulky, but it will get you through a bad week.', 'effect' => ['heal' => 22], 'charges' => 5]);

/* gadgets 6500s */
$item(6500, 'a lockpick gun', 'lockpick pick gun', 'gadget', '', 0.4, 40, ['long_desc' => 'For the doors that still use pins instead of ICE. Loud, but faster than your fingers.', 'stat_mods' => ['tech' => 1]]);
$item(6501, 'an RFID cloner', 'cloner rfid card', 'gadget', 'held', 0.3, 120, ['long_desc' => 'Swipe near a keycard, walk to the door, be someone else. Battery lasts a dozen clones.', 'stat_mods' => ['tech' => 2], 'level_req' => 2]);
$item(6502, 'a grapple line', 'grapple line hook rope', 'gadget', 'waist', 1.0, 90, ['long_desc' => 'Spooled cable and a magnetic hook. Makes "climb" a suggestion rather than a dice roll.', 'stat_mods' => ['reflex' => 1]]);
$item(6503, 'a smoke grenade', 'smoke grenade', 'gadget', '', 0.3, 30, ['long_desc' => 'Pull, drop, disappear. Buys you one clean exit or one messy entrance.', 'charges' => 1]);
$item(6504, 'an EMP charge', 'emp charge grenade', 'gadget', '', 0.4, 160, ['long_desc' => 'A one-shot pulse that browns out drones, cameras and cheap chrome in the room. Yours included, if you are chromed.', 'charges' => 1, 'level_req' => 3, 'flags' => 'illegal']);
$item(6505, 'a camera spike', 'spike camera hack', 'gadget', '', 0.2, 70, ['long_desc' => 'Jam it in a data port and it loops the nearest camera feed for ten minutes. Single use, leaves a mark.', 'charges' => 1, 'stat_mods' => ['tech' => 1]]);
$item(6510, 'a ballistic shield plate', 'shield plate ballistic', 'gear', 'arms', 3.0, 200, ['armor' => 3, 'long_desc' => 'A slab of composite with an arm strap. Heavy on the wrong side, life-saving on the right one.', 'level_req' => 3]);
$item(6511, 'a targeting monocle', 'monocle targeting scope', 'gear', 'eyes', 0.2, 130, ['long_desc' => 'Clip-on optic that ranges and leads a moving target for you. Screams "shooter" to anyone who notices.', 'stat_mods' => ['reflex' => 2], 'level_req' => 3]);
$item(6512, 'a Maelstrom optic (looted)', 'optic maelstrom eye looted', 'gear', 'eyes', 0.2, 90, ['long_desc' => 'Still warm. A red targeting eye prised from a chromehead. Works fine; occasionally shows you what it wants to.', 'stat_mods' => ['reflex' => 1, 'cool' => -1], 'flags' => 'illegal']);
$item(6520, 'Royce\'s ammo rig', 'rig ammo bandolier royce', 'gear', 'torso', 2.0, 240, ['armor' => 2, 'long_desc' => 'A cross-body bandolier Royce never got to empty. Fits a lot of magazines and a certain swagger.', 'stat_mods' => ['reflex' => 1, 'cool' => 1]]);
$item(6521, 'a NetWatch tracer module', 'tracer module netwatch', 'gadget', 'held', 0.3, 260, ['long_desc' => 'Turns a hack into a hunt: follows a signal back to its source. Corp-restricted, obviously.', 'stat_mods' => ['tech' => 2, 'intel' => 1], 'level_req' => 5, 'flags' => 'illegal']);
$item(6522, 'a Scav extraction toolkit', 'toolkit extraction scav tools', 'gadget', '', 1.0, 180, ['long_desc' => 'The kit the surgeons use to take chrome out of the unwilling. In your hands, a very good repair kit. Try not to think about it.', 'stat_mods' => ['tech' => 3], 'flags' => 'illegal']);
$item(6523, 'the Carver\'s trophy chain', 'chain trophy carver necklace', 'gear', 'neck', 0.6, 400, ['long_desc' => 'Dog tags, optic lenses and one corporate lanyard, wired together. Wearing it into the Bazaar gets you served first and watched closely.', 'stat_mods' => ['cool' => 3], 'flags' => 'illegal']);
$item(6524, 'the wire crown', 'crown wire ratking', 'gear', 'head', 0.4, 350, ['long_desc' => 'Stripped copper twisted into a circlet by something with no hands. It hums against your skull and, faintly, you understand the rats.', 'stat_mods' => ['intel' => 2, 'cool' => 1]]);
$item(6525, 'a drone plasma cutter', 'cutter plasma drone torch', 'weapon', 'wield', 2.8, 300, ['dmg' => '2d8', 'level_req' => 5, 'long_desc' => 'Pried off a maintenance drone\'s arm and fitted with a grip. Cuts doors, walls and arguments.', 'flags' => 'melee']);
$item(6526, 'a shard of black ICE', 'shard ice black program', 'gadget', 'held', 0.1, 500, ['long_desc' => 'A crystallised fragment of a rogue security program, still faintly hostile. Slotted into a deck it makes your intrusions bite.', 'stat_mods' => ['tech' => 3, 'intel' => 2], 'level_req' => 6, 'flags' => 'illegal']);
$item(6527, 'a corrupted memory shard', 'shard memory corrupted braindance', 'gadget', '', 0.1, 220, ['long_desc' => 'Someone\'s last upload, gone wrong. Sells to the right collector. Playing it is not recommended and not stoppable once started.', 'flags' => 'illegal']);
$item(6528, 'a Blackwall key-fragment', 'keyfragment fragment blackwall key', 'gadget', 'held', 0.1, 1500, ['long_desc' => 'A sliver of the protocol that holds the wall shut. Terrifying to own. In a deck, doors simply stop being locked.', 'stat_mods' => ['tech' => 4, 'intel' => 3], 'level_req' => 10, 'flags' => 'illegal quest']);

/* legendary / boss 6600 */
$item(6600, 'the Curator\'s Index', 'index curator book legendary', 'gadget', 'held', 0.5, 5000, ['long_desc' => 'Every dead program in the Sculpture Garden, catalogued by something that had nothing but time. Hold it and the whole rotten Net feels navigable.', 'stat_mods' => ['tech' => 5, 'intel' => 5, 'cool' => 2], 'effect' => ['maxenergy' => 15], 'level_req' => 12, 'flags' => 'illegal quest']);

/* light sources 6700 */
$item(6700, 'a wind-up flashlight', 'flashlight torch light', 'light', 'held', 0.3, 15, ['long_desc' => 'Crank it for light. Squeaks. Better than the dark, and the dark down here is total.', 'flags' => 'glow']);
$item(6701, 'a chem-light stick', 'chemlight glowstick light stick', 'light', 'held', 0.1, 3, ['long_desc' => 'Snap it and it glows sour green for a few hours. One use, then it is just a stick.', 'flags' => 'glow', 'charges' => 1]);
$item(6702, 'a headlamp', 'headlamp lamp light', 'light', 'head', 0.3, 40, ['long_desc' => 'Hands-free beam on an elastic strap. The tunnel nomad\'s essential. Blinds people you turn to look at, which has upsides.', 'flags' => 'glow']);

/* containers 6800 */
$item(6800, 'a duffel bag', 'duffel bag holdall', 'container', 'held', 1.0, 30, ['long_desc' => 'A big canvas bag with a broken zip. Holds a lot of things you will tell yourself you need.']);
$item(6801, 'a lockbox', 'lockbox box strongbox', 'container', '', 3.0, 60, ['long_desc' => 'Steel, key long lost, lid forced. Now just a heavy box that keeps small things together.']);

/* misc / junk / quest 6900s */
$item(6900, 'a fistful of eddies', 'eddies eurodollars cash money', 'currency', '', 0.0, 20, ['long_desc' => 'Crumpled Eurodollar scrip. Spends anywhere, judged nowhere.']);
$item(6901, 'a rat tail', 'tail rat', 'junk', '', 0.1, 2, ['long_desc' => 'Proof of a rat, minus the rat. Someone, somewhere, is paying by the tail.']);
$item(6902, 'a bundle of scrip', 'scrip cash bundle', 'junk', '', 0.1, 12, ['long_desc' => 'Grubby low-denomination notes. Sells for face value to anyone; it is money that has not decided to be money yet.']);
$item(6903, 'a stripped circuit board', 'board circuit scrap electronics', 'junk', '', 0.3, 9, ['long_desc' => 'Gold fingers, dead chips. The e-waste pickers weigh these by the kilo.']);
$item(6904, 'a spool of stolen fibre', 'fibre spool cable optic', 'junk', '', 1.0, 35, ['long_desc' => 'Premium optical fibre that fell off a truck. The Wire Yard tech will take all you can carry.']);
$item(6905, 'a dropped credchip', 'credchip chip cred', 'junk', '', 0.05, 40, ['long_desc' => 'A cash chip someone lost in the runoff. Still has a balance on it, if the reader is kind.']);
$item(6910, 'a custom braindance', 'braindance bd custom disc', 'junk', '', 0.1, 30, ['long_desc' => 'A homemade experience disc. The label just says WARM. Best not.']);
$item(6911, 'a waterproof document tube', 'tube document case', 'junk', '', 0.4, 25, ['long_desc' => 'A sealed courier tube. Light. Rattles once. Someone paid to move whatever is inside without opening it, and so should you.', 'flags' => 'quest']);
$item(6912, 'a corporate keycard', 'keycard card corpo access', 'junk', '', 0.02, 30, ['long_desc' => 'An executive\'s access card, photo scratched off. Opens something, somewhere, for a while.', 'flags' => 'quest']);
$item(6913, 'a lump of cyber-scrap', 'scrap cyberscrap chrome junk', 'junk', '', 0.4, 16, ['long_desc' => 'Corroded augment parts fused with tunnel gunk. The tech in Maintenance 12 pays for these; do not ask what for.']);
$item(6914, 'a drone servo', 'servo drone motor part', 'junk', '', 0.5, 28, ['long_desc' => 'A clean actuator out of a dead maintenance drone. Techies will trade for these all day.']);
$item(6915, 'a fragment of hostile code', 'fragment code hostile data', 'junk', '', 0.05, 55, ['long_desc' => 'A quarantined snippet of something that used to be black ICE. Warm to the touch, which it should not be.', 'flags' => 'illegal']);
$item(6916, 'a data ghost\'s locket', 'locket ghost keepsake', 'junk', '', 0.1, 45, ['long_desc' => 'A digital keepsake dropped by a corrupted upload: a looping second of a face, laughing. Someone is still looking for whoever this was.', 'flags' => 'quest']);

/* implants dropped by mobs 2200s referenced in loot */
$item(2201, 'a cracked reflex chip', 'chip reflex cracked implant', 'implant', 'implant_skeleton', 0.2, 300, ['long_desc' => 'A reflex booster with a hairline fault. Works. Occasionally makes you flinch at nothing.', 'stat_mods' => ['reflex' => 2], 'level_req' => 3, 'flags' => 'illegal']);
$item(2202, 'a chromehead strength servo', 'servo strength chromehead implant arm', 'implant', 'implant_arm', 0.9, 500, ['long_desc' => 'Ripped from a Maelstrom heavy. Cruder than a Gorilla Arm, nearly as strong.', 'stat_mods' => ['body' => 2], 'level_req' => 4, 'flags' => 'illegal']);
$item(2210, 'Royce\'s adrenal override', 'override adrenal royce implant', 'implant', 'implant_skeleton', 0.3, 1100, ['long_desc' => 'The gland hack that kept Royce swinging long after he should have dropped. Now it can do that for you.', 'stat_mods' => ['body' => 2, 'reflex' => 2], 'level_req' => 6, 'flags' => 'illegal']);
$item(2211, 'a NetWatch neural firewall', 'firewall neural netwatch implant', 'implant', 'implant_neural', 0.1, 950, ['long_desc' => 'Corp-grade intrusion defence for your own skull. Also just makes you think faster.', 'stat_mods' => ['intel' => 2, 'tech' => 2], 'level_req' => 5, 'flags' => 'illegal']);
$item(2212, 'a Scav-harvested optic', 'optic scav harvested eye implant', 'implant', 'implant_ocular', 0.1, 400, ['long_desc' => 'Someone else\'s eyes, taken the ugly way and barely cleaned. Sees fine. Dreams are not yours anymore.', 'stat_mods' => ['reflex' => 1, 'intel' => 1], 'level_req' => 3, 'flags' => 'illegal']);
$item(2213, 'the Carver\'s subdermal plate', 'plate subdermal carver implant', 'implant', 'implant_dermal', 0.6, 1000, ['long_desc' => 'The armour weave that made the Scav chief so hard to put down. It can do the same for you, once the swelling goes down.', 'armor' => 4, 'stat_mods' => ['body' => 1], 'level_req' => 6, 'flags' => 'illegal']);
$item(2214, 'a rat-nest neural mesh', 'mesh neural ratking implant', 'implant', 'implant_neural', 0.1, 600, ['long_desc' => 'Wire the Rat King grew into something almost like a chip. Slot it and your instincts get uncannily good.', 'stat_mods' => ['reflex' => 2, 'intel' => 1], 'level_req' => 5, 'flags' => 'illegal']);
$item(2215, 'a flood-grown gill implant', 'gill implant flood breather', 'implant', 'implant_dermal', 0.3, 450, ['long_desc' => 'Something the drowned things have. Filters water for air. You will never fear the flooded galleries again.', 'stat_mods' => ['body' => 1], 'level_req' => 4, 'flags' => 'illegal']);
$item(2220, 'a hardened combat cortex', 'cortex combat construct implant', 'implant', 'implant_neural', 0.1, 1300, ['long_desc' => 'Salvaged from a security construct. Threat assessment at machine speed, wired into your fear response.', 'stat_mods' => ['reflex' => 2, 'intel' => 2, 'cool' => 1], 'level_req' => 7, 'flags' => 'illegal']);
$item(2230, 'the Curator\'s lens', 'lens curator ocular implant legendary', 'implant', 'implant_ocular', 0.1, 4000, ['long_desc' => 'An eye that has read the whole dead Net. You see data structures overlaid on the world, and sometimes they see you back.', 'stat_mods' => ['intel' => 3, 'tech' => 3], 'level_req' => 11, 'flags' => 'illegal quest']);

/* ---- SHOPS ---------------------------------------------------- */
$SHOP = [
    // room_vnum, keeper_vnum, name, buy_types, markup, markdown, greeting, [ [vnum,qty,price?], ... ]
    [1004, 5020, "Wakako's Noodle Bar", 'food,drink', 1.20, 0.30, "Sit. Eat. The broth does not wait for your problems.", [
        [6000, -1], [6002, -1], [6003, -1], [6004, -1], [6010, -1], [6011, -1],
    ]],
    [1006, 5021, "Chrome Row Ripperdoc", 'gadget,implant', 1.60, 0.35, "Chair's open. Eddies first, questions never.", [
        [4001, -1], [4002, -1], [4003, 2], [4008, 1], [3012, -1], [6702, -1], [6023, -1], [6021, -1],
        [4041, -1], [4042, -1], [4043, 1], [4044, -1], [4046, -1], [2233, -1], [6539, -1],
    ]],
    [1007, null, "Ramen Row Stalls", 'food', 1.15, 0.25, "", [
        [6001, -1], [6004, -1], [6005, -1], [6002, -1], [6000, -1], [6012, -1],
        [6054, -1], [6057, -1], [6060, -1],
    ]],
    [1008, 5022, "The Gristle", 'weapon,gadget', 1.45, 0.40, "Cash. No trades on ammo. Don't touch what you're not buying.", [
        [1000, -1], [1003, -1], [1004, -1], [1007, -1], [2000, -1], [2001, -1], [2002, 3], [1006, 2], [6503, -1], [6500, -1],
        [1022, -1], [1023, -1], [1025, -1], [2013, -1], [2014, -1], [6541, -1],
    ]],
    [1009, 5023, "Pawnshop 'Last Resort'", '*', 1.70, 0.45, "Selling? Slot it in the tray. Buying? Everything's marked.", [
        [1002, -1], [3000, -1], [3007, -1], [5000, -1], [6700, -1], [6801, -1], [6021, -1],
        [3031, -1], [3032, -1], [3033, -1], [6931, -1], [6932, -1], [6804, -1], [6805, -1],
    ]],
    [1102, 5030, "The Watson Bazaar", '*', 1.40, 0.45, "Whatever it is, we have it in the back. Probably. Eventually.", [
        [3013, -1], [5005, -1], [6022, -1], [6032, -1], [6501, -1], [6505, -1], [6801, -1], [3009, 2], [6510, 2],
    ]],
    [1103, 5031, "Iron-Arm's Iron", 'weapon', 1.35, 0.40, "Prices are the prices. Ammo's cash only.", [
        [1005, -1], [1009, 2], [2004, 3], [2005, 2], [2002, -1], [1006, -1], [6510, -1], [6504, 2],
    ]],
    [1104, null, "Bazaar Chrome Container", 'gadget', 1.55, 0.35, "Fell off a truck. Still in the foam. Sit on the table.", [
        [4003, -1], [4005, 1], [4006, 1], [4007, 1], [4009, 1], [3008, 1], [2201, 2], [6501, -1],
    ]],
    [1105, null, "Bazaar Apothecary", 'drug', 1.50, 0.30, "By colour. Don't mix the reds. Or do. Your funeral.", [
        [6020, -1], [6022, -1], [6030, -1], [6031, -1], [6032, -1], [6033, -1], [6040, 3], [6023, -1], [6069, 2],
    ]],
    [1113, 5036, "Smuggler's Rest", 'food,drink,junk', 1.30, 0.50, "Sit anywhere that isn't someone. First one's not free but the advice is.", [
        [6013, -1], [6014, 4], [6011, -1], [6006, -1], [6010, -1], [6701, -1],
    ]],
    [1205, 5043, "Militech Boutique", 'weapon,armor', 1.90, 0.30, "Everything on the wall is licensed. Everything under it is a conversation.", [
        [2001, -1], [2004, -1], [2006, 2], [3006, -1], [3009, -1], [3021, 1], [6511, -1], [6521, 1],
        [2014, -1], [2016, 2], [3036, 1],
    ]],
    [1207, null, "The Gold Room", 'drink,food', 2.20, 0.20, "If you must ask the price, the door is behind you.", [
        [6014, -1], [6006, -1], [6032, -1],
    ]],
    [1406, 5063, "Maintenance 12 Spares", 'gadget,junk', 1.35, 0.55, "Careful on the panels, half of them bite. Parts are in the crate.", [
        [6700, -1], [6701, -1], [6702, -1], [6500, -1], [6502, -1], [5000, -1], [6023, -1], [6021, -1],
    ]],
];

/* ---- QUESTS -------------------------------------------------- */
$QUEST = [
    // vnum, name, giver_vnum, summary, description, goal_type, goal_target, goal_count, xp, money, reward_vnum, level_req, next_vnum
    [7000, 'Bounce Test', 5024, 'Rogue wants five street rats or junkies off her block. Prove you can finish something.',
        "\"Kabuki's got a vermin problem, two-legged and four. Thin them out - five bodies, I don't care whose - and come back. Small money. Bigger doors.\"",
        'kill', '', 5, 60, 120, 6021, 1, 7001],
    [7001, 'The Noodle Debt', 5024, 'A braindance dealer owes Rogue. Lean on him until he pays - one dealer down.',
        "\"There's a BD dealer working the parlour doors who thinks I forgot what he owes. Remind him. Loudly. Then we'll talk about real work.\"",
        'kill', 'dealer', 1, 90, 180, 1003, 2, 7002],
    [7002, 'Claws Off', 5024, 'Send the Tyger Claws a message: drop three enforcers in their own district.',
        "\"The Claws are squeezing my people for protection twice a week now. Put three of their enforcers on the ground where their friends can see. That's the whole job.\"",
        'kill', 'enforcer', 3, 160, 320, 3020, 3, 0],
    [7010, 'Courier Run', 5025, 'Ozob needs a sealed document tube carried to the Bazaar. Grab it from the dead drop and go.',
        "\"Package in the dead drop out in the container maze. Don't open it, don't scan it, don't lose it. Get it, then bring it to me - I'll be about.\"",
        'collect', 'tube', 1, 80, 160, 6011, 2, 7011],
    [7011, 'Long Way Round', 5025, 'The buyer got cold feet. Ozob wants eyes on the Warehouse 7 boss - get in, get a look, get out.',
        "\"New plan. Before I move that package I need to know if Warehouse 7's still Maelstrom. Walk in, lay eyes on whoever's running it, walk out. Alive is the grade.\"",
        'visit', '1110', 1, 200, 260, 6031, 4, 0],
    [7020, 'Rat Control', 5063, 'The tunnel tech will pay per rat tail. Bring six.',
        "\"Rats are chewing the district's water lines faster than I can patch. Six tails, and I'll make it worth the walk. Watch the big ones - they've stopped running.\"",
        'collect', 'tail', 6, 110, 180, 6702, 2, 7021],
    [7021, 'The Sealed Door', 5063, 'Something in the buried server vault is still powered. The tech wants it shut down - kill whatever is running it.',
        "\"That vault behind the blast door - the corp left it running and now the readings are climbing. Whatever's keeping the lights on in there, put it out. Please.\"",
        'kill', 'ratking', 1, 260, 400, 2214, 5, 0],
    [7030, 'Dry Goods', 5036, 'The barge bartender is short on stock. Bring three bottles of top-shelf sake from anywhere uptown.',
        "\"My sake supplier got pinched at the checkpoint. I'm dry in a week. Three bottles, top shelf, don't care how you come by them. Regulars are getting twitchy.\"",
        'collect', 'sake', 3, 90, 150, 6013, 3, 7031],
    [7031, 'Seven Out', 5036, 'A Maelstrom crew is muscling in on the bartender\'s dock routes. Put down four of them.',
        "\"Maelstrom's been taxing my runners on Pier 9. Four of them, gone, and word gets around that this bar is not worth the trouble. That's the arrangement.\"",
        'kill', 'maelstrom', 4, 240, 380, 6520, 5, 0],
    [7040, 'Head of the Table', 5024, 'Rogue has heard about the Scav nest in the Arroyo Mall. End the chief.',
        "\"There's a Scav chief in the Combat Zone running an organ line out of a dead mall. Trauma Team won't touch it, NCPD won't touch it. You will. Bring me the saw off his belt.\"",
        'kill', 'chief', 1, 400, 700, 6522, 7, 0],
    [7050, 'What The Water Kept', 5062, 'A tunnel nomad lost a friend to the flooded gallery. Recover the data ghost\'s locket from the Fringe.',
        "\"One of ours uploaded before the flood took the body. Whatever came back isn't her, but it carries a locket - a second of her laughing. Bring it home so we can bury something.\"",
        'collect', 'locket', 1, 300, 320, 6702, 8, 0],
    [7060, 'The Curator', 5024, 'Rogue is done asking nicely. Someone has to go out to the Blackwall Fringe and shut the Curator down for good.',
        "\"Runners keep going dark out on the Fringe. Something out there is collecting them. I don't send people to die, so I'm sending you - because if anyone walks back out of that datacentre, it's the one who did all the rest of this. Kill it. Bring me the Index.\"",
        'kill', 'curator', 1, 900, 2500, 5004, 10, 0],
];

/* ============================================================
 *  BUILD
 * ============================================================ */

if ($statsOnly) {
    foreach (['mud_zones', 'mud_rooms', 'mud_exits', 'mud_room_extras', 'mud_item_templates', 'mud_mob_templates', 'mud_mob_instances', 'mud_shops', 'mud_shop_stock', 'mud_quests', 'mud_players'] as $t) {
        printf("  %-22s %d\n", $t, (int) Db::val("SELECT COUNT(*) FROM `$t`"));
    }
    exit(0);
}

echo "Hackers-MUD world builder\n=========================\n";

/* exits: [from_vnum, dir, to_vnum, opts]  - auto reverse unless opts[oneway] */
$EX = [
    // Kabuki internal
    [1000, 'd', 1001], [1001, 'd', 1002], [1002, 'n', 1003], [1003, 'e', 1004],
    [1003, 'n', 1005], [1005, 'w', 1006], [1005, 'e', 1007], [1005, 'n', 1008],
    [1008, 'w', 1009], [1002, 'e', 1010], [1010, 'e', 1011], [1011, 'e', 1012, ['keyword' => 'door', 'descr' => 'The Afterlife\'s door. The bouncer sizes you up and, eventually, steps aside.']],
    [1002, 'w', 1013], [1013, 'n', 1014], [1014, 'w', 1015], [1008, 'n', 1016], [1016, 'n', 1017],
    [1017, 'd', 1400, ['descr' => 'The drain throat drops into the dark of the Undercity.']],
    [1010, 'ne', 1019], [1005, 'se', 1020], [1003, 'w', 1021],
    // Kabuki -> Watson
    [1016, 'w', 1100, ['descr' => 'A walkway leads west toward the canal and the docks.']],
    // Watson internal
    [1100, 'n', 1101], [1101, 'n', 1102], [1102, 'w', 1103], [1102, 'e', 1104], [1102, 'n', 1105],
    [1101, 'e', 1106], [1106, 'e', 1107], [1105, 'n', 1108], [1108, 'u', 1109], [1109, 'n', 1110],
    [1103, 's', 1111], [1111, 'w', 1112], [1111, 's', 1113], [1104, 'e', 1114],
    [1114, 's', 1115], [1115, 's', 1116], [1116, 'd', 1401, ['descr' => 'The middle drain slopes down into the Undercity.']],
    [1100, 'w', 1117], [1117, 'n', 1201, ['keyword' => 'checkpoint', 'hack_dc' => 6, 'descr' => 'The security turnstile. Past it, the maglev platform and the plaza gleam.']],
    // Corpo internal
    [1200, 's', 1201], [1200, 'n', 1202], [1202, 'u', 1203], [1203, 'u', 1204, ['keyword' => 'door', 'locked' => 1, 'hack_dc' => 12, 'descr' => 'A glass security door to the server floor. Badge or breach.']],
    [1200, 'w', 1205], [1200, 'e', 1206], [1206, 'n', 1207], [1200, 'nw', 1208],
    [1200, 'u', 1209], [1209, 'e', 1210], [1210, 'd', 1211], [1211, 'n', 1212], [1212, 'nw', 1200],
    [1210, 'n', 1204, ['keyword' => 'hatch', 'hidden' => 1, 'descr' => 'An unlocked maintenance hatch opens onto the server floor from behind.']],
    // Combat Zone - reached from the plaza loading dock / undercity
    [1300, 's', 1212, ['descr' => 'A cracked service road climbs south out of the mall lot toward the plaza loading dock.']],
    [1300, 'n', 1301], [1301, 'n', 1302], [1302, 'u', 1303], [1303, 'w', 1304], [1303, 'e', 1305],
    [1303, 'u', 1306], [1306, 'w', 1307], [1306, 'u', 1308], [1301, 'e', 1309], [1309, 's', 1310],
    [1311, 'd', 1404, ['descr' => 'The fire stair only goes down, into cold tunnel air.']],
    [1300, 'w', 1312], [1312, 'n', 1311],
    // Undercity internal
    [1400, 'n', 1401], [1400, 'w', 1402], [1400, 'e', 1403], [1401, 'n', 1404], [1404, 'n', 1405],
    [1400, 's', 1406], [1401, 'e', 1407], [1404, 'e', 1408], [1408, 'n', 1409, ['keyword' => 'door', 'locked' => 1, 'hack_dc' => 14, 'descr' => 'The blast door. The keypad hangs open, wires bared.']],
    [1405, 'w', 1410], [1405, 'n', 1411], [1411, 'n', 1500, ['descr' => 'The comms conduit crawls north toward the Fringe datacentres.']],
    // Blackwall internal
    [1500, 'n', 1501], [1500, 'w', 1502], [1501, 'n', 1503], [1501, 'e', 1504], [1503, 'n', 1505],
    [1505, 'n', 1506, ['keyword' => 'jack', 'descr' => 'You lie back on the couch and jack in. The room falls away.']],
    [1506, 'n', 1507],
];

/* ---- content expansion: extra rooms, mobs, items, shops, quests, lore ----
   Split into its own file to keep this one readable. It uses the $room/$mob/
   $item closures and appends to $SHOP / $QUEST / $EX, and fills these: */
$EXTRAS    = [];   // [room_vnum => [[keywords, body], ...]]  - readable room lore
$SPAWN_EXT = [];   // [mob_vnum  => [[room_vnum, count], ...]]
if (is_file(__DIR__ . '/mud_world_ext.php')) {
    require __DIR__ . '/mud_world_ext.php';
}

// Normalise any stray PHP escape artefacts (a literal \' or \" that slipped in
// via a double-quoted source string) before anything reaches the database.
$fixEsc = static function (&$v): void {
    if (is_string($v)) {
        $v = strtr($v, ["\\'" => "'", '\\"' => '"']);
    }
};
array_walk_recursive($R, $fixEsc);
array_walk_recursive($MOB, $fixEsc);
array_walk_recursive($IT, $fixEsc);
array_walk_recursive($SHOP, $fixEsc);
array_walk_recursive($QUEST, $fixEsc);
array_walk_recursive($EX, $fixEsc);
array_walk_recursive($EXTRAS, $fixEsc);

Db::pdo()->beginTransaction();
try {
    // Room ids change on every rebuild, so snapshot each player's LOCATION by
    // room vnum and their carried/worn items by item vnum, then restore both
    // after the world is rebuilt. Players keep level, skills, eddies, quests.
    $keptItems = [];
    $keptWhere = [];
    $havePlayers = Db::val("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'mud_players'")
        && (int) Db::val("SELECT COUNT(*) FROM mud_players") > 0;
    if ($havePlayers) {
        $keptItems = Db::all(
            "SELECT ii.loc_id AS player_id, pe.slot AS slot, it.vnum AS vnum,
                    ii.`condition` AS `condition`, ii.charges_left AS charges_left, ii.custom_name AS custom_name
             FROM mud_item_instances ii
             JOIN mud_item_templates it ON it.id = ii.template_id
             LEFT JOIN mud_player_equipment pe ON pe.instance_id = ii.id
             WHERE ii.loc_type = 'player'"
        );
        $keptWhere = Db::all(
            "SELECT p.id AS player_id, r1.vnum AS room_vnum, r2.vnum AS respawn_vnum
             FROM mud_players p
             LEFT JOIN mud_rooms r1 ON r1.id = p.room_id
             LEFT JOIN mud_rooms r2 ON r2.id = p.respawn_room_id"
        );
    }

    // wipe world + templates + instances; keep player characters, skills, quests
    foreach ([
        'mud_exits', 'mud_shop_stock', 'mud_shops', 'mud_mob_instances', 'mud_item_instances',
        'mud_mob_templates', 'mud_item_templates', 'mud_room_extras', 'mud_rooms', 'mud_zones', 'mud_quests',
        'mud_player_equipment', 'mud_player_effects',
    ] as $t) {
        if (Db::val("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?", [$t])) {
            Db::q("DELETE FROM `$t`");
        }
    }
    echo '  wiped world tables' . ($keptItems ? ' (snapshotted ' . count($keptItems) . ' player item(s))' : '') . "\n";

    // zones
    $zoneId = [];
    $zn = 1;
    foreach ($ZONES as $slug => $z) {
        Db::q('INSERT INTO mud_zones (id, slug, name, description, level_min, level_max, respawn_secs) VALUES (?,?,?,?,?,?,200)',
            [$zn, $slug, $z[0], $z[1], $z[2], $z[3]]);
        $zoneId[$slug] = $zn++;
    }
    echo '  zones: ' . count($zoneId) . "\n";

    // rooms
    $roomId = [];
    foreach ($R as $vnum => $r) {
        $id = Db::insert('mud_rooms', [
            'vnum' => $vnum, 'zone_id' => $zoneId[$r['zone']], 'name' => $r['name'],
            'description' => $r['desc'], 'x' => $r['x'], 'y' => $r['y'], 'z' => $r['z'],
            'flags' => $r['flags'], 'light' => str_contains($r['flags'], 'dark') ? 0 : 1,
        ]);
        $roomId[$vnum] = $id;
    }
    echo '  rooms: ' . count($roomId) . "\n";

    // exits: TWO PASSES.
    //   pass 1  inserts every EXPLICIT [from,dir,to] from $EX. An explicit exit
    //           always wins its own from:dir slot; a genuinely doubled explicit
    //           entry is de-duped (and warned about) so it is harmless.
    //   pass 2  adds the auto-reverse for each non-oneway explicit exit, but
    //           ONLY where "$to:$rev" is still empty - an explicit exit is never
    //           clobbered by, and never silently loses to, an auto-reverse.
    // The reverse inherits keyword/locked/hidden/hack_dc (a door is a door from
    // both sides) but gets a plain description.
    $exCount = 0;
    $seen = [];
    $REV = ['n' => 's', 's' => 'n', 'e' => 'w', 'w' => 'e', 'u' => 'd', 'd' => 'u',
            'ne' => 'sw', 'nw' => 'se', 'se' => 'nw', 'sw' => 'ne', 'in' => 'out', 'out' => 'in'];
    $mkExit = function ($f, $d, $t, array $o) use (&$roomId, &$exCount, &$seen): bool {
        $key = "$f:$d";
        if (isset($seen[$key])) {
            return false;
        }
        $seen[$key] = true;
        Db::insert('mud_exits', [
            'from_room' => $roomId[$f], 'dir' => $d, 'to_room' => $roomId[$t],
            'keyword' => $o['keyword'] ?? '', 'locked' => $o['locked'] ?? 0,
            'key_vnum' => $o['key_vnum'] ?? null,
            'hidden' => $o['hidden'] ?? 0, 'hack_dc' => $o['hack_dc'] ?? 0,
            'descr' => $o['descr'] ?? '',
        ]);
        $exCount++;
        return true;
    };
    $explicit = [];
    foreach ($EX as $e) {
        [$from, $dir, $to] = $e;
        $opt = $e[3] ?? [];
        if (!isset($roomId[$from], $roomId[$to])) {
            fwrite(STDERR, "  ! exit skips missing room $from->$to\n");
            continue;
        }
        if ($mkExit($from, $dir, $to, $opt)) {
            $explicit[] = [$from, $dir, $to, $opt];
        } else {
            fwrite(STDERR, "  ! duplicate explicit exit $from:$dir dropped\n");
        }
    }
    foreach ($explicit as [$from, $dir, $to, $opt]) {
        if (!empty($opt['oneway'])) {
            continue;
        }
        $rev = $REV[$dir] ?? null;
        if ($rev === null || isset($seen["$to:$rev"])) {
            continue;
        }
        $ro = $opt;
        unset($ro['descr'], $ro['oneway']); // reverse gets a plain description
        $mkExit($to, $rev, $from, $ro + ['descr' => '']);
    }
    echo "  exits: $exCount\n";

    // item templates
    $itemId = [];
    foreach ($IT as $vnum => $it) {
        $itemId[$vnum] = Db::insert('mud_item_templates', [
            'vnum' => $vnum, 'name' => $it['name'], 'keywords' => $it['kw'],
            'room_desc' => $it['room_desc'] ?: (ucfirst($it['name']) . ' lies here.'),
            'long_desc' => $it['long_desc'] ?: $it['name'],
            'type' => $it['type'],
            'icon' => $it['icon'] ?? \Bbs\Mud\Icons::forItem($it['name'], $it['kw'], $it['type'], (string) $it['slot'], (string) $it['flags']),
            'slot' => $it['slot'], 'weight' => $it['weight'], 'value' => $it['value'],
            'damage_dice' => $it['dmg'], 'armor' => $it['armor'],
            'stat_mods' => $it['stat_mods'] ? json_encode($it['stat_mods']) : null,
            'effect' => $it['effect'] ? json_encode($it['effect']) : null,
            'charges' => $it['charges'], 'level_req' => $it['level_req'], 'flags' => $it['flags'],
        ]);
    }
    echo '  item templates: ' . count($IT) . "\n";

    // re-grant the player items we snapshotted before the wipe
    $regrant = 0;
    foreach ($keptItems as $k) {
        if (!isset($itemId[(int) $k['vnum']])) {
            continue;   // that item no longer exists in the world - drop it
        }
        $newId = Db::insert('mud_item_instances', [
            'template_id'  => $itemId[(int) $k['vnum']],
            'loc_type'     => 'player',
            'loc_id'       => (int) $k['player_id'],
            'condition'    => (int) ($k['condition'] ?? 100),
            'charges_left' => (int) ($k['charges_left'] ?? -1),
            'custom_name'  => $k['custom_name'] ?: null,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
        if (!empty($k['slot'])) {
            Db::insert('mud_player_equipment', [
                'player_id'   => (int) $k['player_id'],
                'slot'        => $k['slot'],
                'instance_id' => $newId,
            ]);
        }
        $regrant++;
    }
    if ($regrant) {
        echo "  re-granted $regrant player item(s)\n";
    }

    // mob templates
    $mobId = [];
    foreach ($MOB as $vnum => $m) {
        $id = Db::insert('mud_mob_templates', [
            'vnum' => $vnum, 'name' => $m['name'], 'keywords' => $m['kw'],
            'room_desc' => $m['roomdesc'], 'long_desc' => $m['longdesc'],
            'level' => $m['lvl'], 'max_hp' => $m['hp'], 'stats' => json_encode($m['stats']),
            'ac' => $m['ac'], 'damage_dice' => $m['dmg'], 'xp_reward' => $m['xp'],
            'money_min' => $m['mmin'], 'money_max' => $m['mmax'], 'faction' => $m['faction'],
            'behavior' => $m['behavior'], 'dialogue' => $m['dialogue'] ? json_encode($m['dialogue']) : null,
            'loot_table' => $m['loot'] ? json_encode($m['loot']) : null,
            'respawn_secs' => $m['respawn'], 'flags' => $m['flags'],
        ]);
        $mobId[$vnum] = $id;
    }
    echo '  mob templates: ' . count($mobId) . "\n";

    // mob spawns: [mob_vnum => [[room_vnum, count], ...]]
    $SPAWN = [
        5000 => [[1016, 1], [1020, 2], [1017, 1]],
        5001 => [[1013, 1], [1020, 1], [1006, 1], [1101, 1]],
        5002 => [[1010, 1], [1016, 2], [1014, 1]],
        5003 => [[1005, 1], [1008, 1], [1002, 1]],
        5004 => [[1002, 1], [1005, 1], [1003, 1]],
        5010 => [[1010, 1], [1013, 1], [1019, 1], [1014, 1]],
        5011 => [[1015, 1], [1011, 1]],
        5012 => [[1002, 1], [1019, 1]],
        5020 => [[1004, 1]],
        5021 => [[1006, 1]],
        5022 => [[1008, 1]],
        5023 => [[1009, 1]],
        5024 => [[1012, 1]],
        5025 => [[1013, 1]],
        5026 => [[1021, 1]],
        5030 => [[1102, 1]],
        5031 => [[1103, 1]],
        5032 => [[1101, 1], [1106, 2], [1107, 1]],
        5033 => [[1108, 2], [1109, 1]],
        5034 => [[1110, 1]],
        5035 => [[1111, 1], [1115, 1], [1112, 1]],
        5036 => [[1113, 1]],
        5040 => [[1202, 2], [1211, 1], [1212, 1]],
        5041 => [[1204, 1], [1203, 1]],
        5042 => [[1200, 1], [1201, 1], [1209, 1]],
        5043 => [[1205, 1]],
        5044 => [[1206, 1]],
        5045 => [[1200, 1]],
        5050 => [[1300, 1], [1301, 2], [1312, 1]],
        5051 => [[1301, 1], [1303, 2], [1302, 1], [1309, 1]],
        5052 => [[1304, 1]],
        5053 => [[1305, 1]],
        5054 => [[1306, 1], [1307, 1], [1308, 1]],
        5060 => [[1401, 2], [1403, 1], [1407, 2], [1410, 1]],
        5061 => [[1402, 1]],
        5062 => [[1405, 2], [1404, 1]],
        5063 => [[1406, 1]],
        5064 => [[1403, 1], [1407, 1], [1410, 1]],
        5070 => [[1500, 2], [1502, 1], [1504, 1]],
        5071 => [[1501, 1], [1500, 1]],
        5072 => [[1502, 1], [1503, 1]],
        5073 => [[1507, 1]],
    ];
    foreach ($SPAWN_EXT as $mv => $spots) {
        $SPAWN[$mv] = array_merge($SPAWN[$mv] ?? [], $spots);
    }
    $spawnCount = 0;
    foreach ($SPAWN as $mvnum => $spots) {
        foreach ($spots as [$rvnum, $n]) {
            if (!isset($mobId[$mvnum], $roomId[$rvnum])) {
                fwrite(STDERR, "  ! spawn skips $mvnum @ $rvnum\n");
                continue;
            }
            for ($i = 0; $i < $n; $i++) {
                Db::insert('mud_mob_instances', [
                    'template_id' => $mobId[$mvnum], 'room_id' => $roomId[$rvnum],
                    'spawn_room_id' => $roomId[$rvnum], 'hp' => $MOB[$mvnum]['hp'],
                    'state' => 'idle', 'last_act_at' => date('Y-m-d H:i:s'),
                ]);
                $spawnCount++;
            }
        }
    }
    echo "  mob spawns: $spawnCount\n";

    // shops
    $shopN = 0;
    $stockN = 0;
    foreach ($SHOP as $s) {
        [$rvnum, $keeper, $name, $buy, $markup, $markdown, $greet, $stock] = $s;
        if (!isset($roomId[$rvnum])) {
            fwrite(STDERR, "  ! shop skips missing room $rvnum\n");
            continue;
        }
        $sid = Db::insert('mud_shops', [
            'room_id' => $roomId[$rvnum], 'keeper_vnum' => $keeper,
            'name' => $name, 'buy_types' => $buy, 'sell_markup' => $markup,
            'buy_markdown' => $markdown, 'greeting' => $greet,
        ]);
        $shopN++;
        foreach ($stock as $row) {
            Db::insert('mud_shop_stock', [
                'shop_id' => $sid, 'template_vnum' => $row[0],
                'qty' => $row[1] ?? -1, 'price_override' => $row[2] ?? null,
            ]);
            $stockN++;
        }
    }
    echo "  shops: $shopN  (stock rows: $stockN)\n";

    // quests
    foreach ($QUEST as $q) {
        Db::insert('mud_quests', [
            'vnum' => $q[0], 'name' => $q[1], 'giver_vnum' => $q[2], 'summary' => $q[3],
            'description' => $q[4], 'goal_type' => $q[5], 'goal_target' => $q[6], 'goal_count' => $q[7],
            'reward_xp' => $q[8], 'reward_money' => $q[9], 'reward_vnum' => $q[10],
            'level_req' => $q[11], 'next_vnum' => $q[12] ?: null,
        ]);
    }
    echo '  quests: ' . count($QUEST) . "\n";

    // room lore / readable extras
    $exN = 0;
    foreach ($EXTRAS as $rvnum => $rows) {
        if (!isset($roomId[$rvnum])) {
            fwrite(STDERR, "  ! extra skips missing room $rvnum\n");
            continue;
        }
        foreach ($rows as [$kw, $body]) {
            Db::insert('mud_room_extras', ['room_id' => $roomId[$rvnum], 'keywords' => $kw, 'body' => $body]);
            $exN++;
        }
    }
    echo "  room extras: $exN\n";

    // config
    $startId = $roomId[1000];
    foreach ([
        'start_room' => (string) $startId,
        'respawn_room' => (string) $startId,
        'last_tick' => '0',
        'tick_lock' => '0',
        'world_built_at' => date('c'),
    ] as $k => $v) {
        Db::q('INSERT INTO mud_config (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)', [$k, $v]);
    }
    echo "  config: start/respawn room = $startId\n";

    // restore each player's location (mapped through room vnum), clear any
    // transient fight state; level / skills / eddies / quests are untouched.
    $relocated = 0;
    foreach ($keptWhere as $w) {
        $room    = $roomId[(int) $w['room_vnum']]    ?? $startId;
        $respawn = $roomId[(int) $w['respawn_vnum']] ?? $startId;
        Db::q(
            'UPDATE mud_players SET room_id = ?, respawn_room_id = ?, state = "idle", target_mob = NULL WHERE id = ?',
            [$room, $respawn, (int) $w['player_id']]
        );
        $relocated++;
    }
    if ($relocated) {
        echo "  restored $relocated player(s) to their last location\n";
    }

    Db::pdo()->commit();
    echo "\nDone. World is live.\n";
} catch (\Throwable $ex) {
    Db::pdo()->rollBack();
    fwrite(STDERR, "\nFAILED: " . $ex->getMessage() . "\n" . $ex->getTraceAsString() . "\n");
    exit(1);
}
