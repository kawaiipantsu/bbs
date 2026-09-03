<?php
/* Generate mysql/migrations/2026_09_04_20_trivia.sql from a question bank.
   answer index is 0-based into [a,b,c,d]. */
declare(strict_types=1);

/* [category, question, [a,b,c,d], answerIndex, note] */
$Q = [];
$add = function (string $cat, string $q, array $opts, int $ans, string $note = '') use (&$Q) {
    $Q[] = [$cat, $q, $opts, $ans, $note];
};

/* ---------- Danish geography ---------- */
$add('dk-geo', 'What is the capital of Denmark?', ['Aarhus', 'Odense', 'Copenhagen', 'Aalborg'], 2);
$add('dk-geo', 'On which island does Copenhagen mostly lie?', ['Funen', 'Zealand', 'Jutland', 'Bornholm'], 1);
$add('dk-geo', 'Denmark shares its only land border with which country?', ['Sweden', 'Norway', 'Germany', 'Poland'], 2);
$add('dk-geo', 'What connects Zealand and Funen by road and rail?', ['The Oresund Bridge', 'The Little Belt Bridge', 'The Great Belt Fixed Link', 'The Fehmarn tunnel'], 2);
$add('dk-geo', 'Which Danish island lies farthest east, in the Baltic Sea?', ['Aero', 'Mon', 'Bornholm', 'Samso'], 2);
$add('dk-geo', 'The Oresund Bridge links Copenhagen to which city?', ['Oslo', 'Hamburg', 'Malmo', 'Gothenburg'], 2);
$add('dk-geo', 'Roughly how many islands make up Denmark (mapped, over 100 sq m)?', ['About 50', 'About 90', 'Over 400', 'Exactly 100'], 2, 'Around 440 islands, ~70 inhabited.');
$add('dk-geo', 'Which is the second-largest city in Denmark by population?', ['Odense', 'Aalborg', 'Esbjerg', 'Aarhus'], 3);
$add('dk-geo', 'The Faroe Islands and Greenland are part of what?', ['The EU', 'The Kingdom of Denmark', 'Norway', 'Iceland'], 1);
$add('dk-geo', 'What is the name of the large peninsula that is mainland Denmark?', ['Zealand', 'Scania', 'Jutland', 'Funen'], 2);
$add('dk-geo', 'Which sea lies west of Jutland?', ['The Baltic Sea', 'The North Sea', 'The Kattegat', 'The Skagerrak'], 1);
$add('dk-geo', 'Denmarks highest natural point, Mollehoj, is about how tall?', ['171 m', '341 m', '870 m', '1,050 m'], 0, 'Mollehoj is ~170.86 m.');

/* ---------- Danish politics ---------- */
$add('dk-pol', 'What form of government does Denmark have?', ['A republic', 'A constitutional monarchy', 'A federal union', 'A city-state'], 1);
$add('dk-pol', 'What is the Danish parliament called?', ['The Storting', 'The Riksdag', 'The Folketing', 'The Althing'], 2);
$add('dk-pol', 'How many seats does the Folketing have?', ['150', '179', '200', '349'], 1, '175 from Denmark + 2 Faroe Islands + 2 Greenland.');
$add('dk-pol', 'In what year did Denmark join the (then) European Economic Community?', ['1957', '1973', '1986', '1993'], 1);
$add('dk-pol', 'Which currency does Denmark use?', ['The euro', 'The Danish krone', 'The Swedish krona', 'The mark'], 1);
$add('dk-pol', 'Denmark has an opt-out from which EU policy area, among others?', ['Free movement', 'The single market', 'The euro', 'The customs union'], 2);
$add('dk-pol', 'The Danish constitution (Grundloven) is celebrated on which date?', ['1 May', '5 June', '16 April', '24 December'], 1);
$add('dk-pol', 'Who becomes head of state of Denmark in 2024?', ['Queen Margrethe II', 'King Frederik X', 'King Christian XI', 'President of Denmark'], 1, 'Frederik X acceded on 14 January 2024.');
$add('dk-pol', 'Danish governments are usually formed as what?', ['Single-party majorities', 'Coalitions / minority governments', 'Military juntas', 'Technocratic cabinets'], 1);
$add('dk-pol', 'What is the Danish head of government called?', ['President', 'Chancellor', 'Statsminister (Prime Minister)', 'First Consul'], 2);
$add('dk-pol', 'Voting age for Danish national elections is?', ['16', '18', '21', '25'], 1);

/* ---------- Danish famous people ---------- */
$add('dk-ppl', 'Who wrote "The Little Mermaid" and "The Ugly Duckling"?', ['Karen Blixen', 'Hans Christian Andersen', 'Soren Kierkegaard', 'Ludvig Holberg'], 1);
$add('dk-ppl', 'Which Danish physicist won the 1922 Nobel Prize for atomic structure?', ['Tycho Brahe', 'Ole Romer', 'Niels Bohr', 'Hans Christian Orsted'], 2);
$add('dk-ppl', 'Danish drummer and co-founder of Metallica?', ['Lars Ulrich', 'Mille Petrozza', 'Ulrich Schnauss', 'Lars von Trier'], 0);
$add('dk-ppl', 'Which Dane played Le Chiffre in "Casino Royale" and Hannibal on TV?', ['Nikolaj Coster-Waldau', 'Mads Mikkelsen', 'Viggo Mortensen', 'Ulrich Thomsen'], 1);
$add('dk-ppl', 'LEGO was founded by which Danish carpenter?', ['Ole Kirk Christiansen', 'Arne Jacobsen', 'J.C. Jacobsen', 'Georg Jensen'], 0);
$add('dk-ppl', 'Carlsberg brewery was founded in 1847 by?', ['Ole Kirk Christiansen', 'J.C. Jacobsen', 'Niels Bohr', 'Emil Christian Hansen'], 1);
$add('dk-ppl', 'Which Danish philosopher is called a father of existentialism?', ['Soren Kierkegaard', 'Grundtvig', 'Georg Brandes', 'Harald Hoffding'], 0);
$add('dk-ppl', 'Astronomer who ran the Uraniborg observatory and lost his nose in a duel?', ['Ole Romer', 'Tycho Brahe', 'Johannes Kepler', 'Peder Horrebow'], 1);
$add('dk-ppl', 'Danish director behind "Dogville", "Melancholia" and the Dogme 95 movement?', ['Thomas Vinterberg', 'Bille August', 'Lars von Trier', 'Nicolas Winding Refn'], 2);
$add('dk-ppl', 'Which Dane directed "Drive" (2011)?', ['Lars von Trier', 'Susanne Bier', 'Nicolas Winding Refn', 'Tobias Lindholm'], 2);
$add('dk-ppl', 'Author of "Out of Africa", pen name Isak Dinesen?', ['Karen Blixen', 'Tove Ditlevsen', 'Inger Christensen', 'Herman Bang'], 0);
$add('dk-ppl', 'Ole Romer is remembered for first measuring what, in 1676?', ['The speed of light', 'Atmospheric pressure', 'The Earth\'s circumference', 'Absolute zero'], 0);

/* ---------- basic / general knowledge ---------- */
$add('basic', 'How many continents are there on Earth?', ['5', '6', '7', '8'], 2);
$add('basic', 'What is the chemical symbol for gold?', ['Gd', 'Au', 'Ag', 'Go'], 1);
$add('basic', 'How many minutes are in a full day?', ['1000', '1200', '1440', '2400'], 2);
$add('basic', 'Which planet is known as the Red Planet?', ['Venus', 'Jupiter', 'Mars', 'Mercury'], 2);
$add('basic', 'What is the largest ocean on Earth?', ['Atlantic', 'Indian', 'Arctic', 'Pacific'], 3);
$add('basic', 'How many sides does a hexagon have?', ['5', '6', '7', '8'], 1);
$add('basic', 'Water freezes at what temperature in Celsius?', ['0', '10', '32', '100'], 0);
$add('basic', 'Which is the smallest prime number?', ['0', '1', '2', '3'], 2);
$add('basic', 'What gas do plants primarily absorb for photosynthesis?', ['Oxygen', 'Nitrogen', 'Carbon dioxide', 'Hydrogen'], 2);
$add('basic', 'The Great Barrier Reef lies off the coast of which country?', ['Brazil', 'Australia', 'Mexico', 'Thailand'], 1);
$add('basic', 'How many bits are in a byte?', ['4', '8', '16', '32'], 1);
$add('basic', 'What is the tallest mountain above sea level?', ['K2', 'Mount Kilimanjaro', 'Mount Everest', 'Denali'], 2);

/* ---------- fun / quirky ---------- */
$add('fun', 'A group of crows is called a what?', ['A murder', 'A parliament', 'A gaggle', 'A pod'], 0);
$add('fun', 'What is the only letter not appearing in any US state name?', ['Q', 'Z', 'X', 'J'], 0);
$add('fun', 'Honey never does what?', ['Freezes', 'Spoils', 'Dissolves', 'Crystallise'], 1);
$add('fun', 'What colour is a polar bear\'s skin under its fur?', ['White', 'Pink', 'Black', 'Grey'], 2);
$add('fun', 'How many hearts does an octopus have?', ['1', '2', '3', '5'], 2);
$add('fun', 'The fear of long words is ironically named hippopotomonstrosesqui-what-phobia?', ['pedalio', 'pedalia', 'ppedaliophobia', 'pedaliophobia'], 3);
$add('fun', 'What do you call a baby kangaroo?', ['A cub', 'A kit', 'A joey', 'A pup'], 2);
$add('fun', 'Bananas are botanically classified as what?', ['Vegetables', 'Berries', 'Nuts', 'Drupes'], 1);
$add('fun', 'The dot over a lowercase "i" or "j" is called a what?', ['Tittle', 'Pilcrow', 'Serif', 'Umlaut'], 0);
$add('fun', 'A "baker\'s dozen" is how many?', ['11', '12', '13', '14'], 2);
$add('fun', 'What is the most common surname in the world?', ['Smith', 'Wang', 'Garcia', 'Kim'], 1);
$add('fun', 'Which animal can sleep for up to three years at a time?', ['Sloth', 'Snail', 'Bear', 'Bat'], 1);

/* ---------- movie quotes (name the film) ---------- */
$add('movie-quote', '"I\'ll be back."', ['RoboCop', 'The Terminator', 'Predator', 'Total Recall'], 1);
$add('movie-quote', '"Here\'s looking at you, kid."', ['Casablanca', 'Gone with the Wind', 'Citizen Kane', 'The Maltese Falcon'], 0);
$add('movie-quote', '"May the Force be with you."', ['Star Trek', 'Dune', 'Star Wars', 'Flash Gordon'], 2);
$add('movie-quote', '"You can\'t handle the truth!"', ['A Few Good Men', 'The Firm', 'JFK', 'Primal Fear'], 0);
$add('movie-quote', '"Life is like a box of chocolates."', ['Big', 'Forrest Gump', 'Cast Away', 'The Green Mile'], 1);
$add('movie-quote', '"Why so serious?"', ['Batman Begins', 'The Dark Knight', 'Joker', 'Watchmen'], 1);
$add('movie-quote', '"They may take our lives, but they\'ll never take our freedom!"', ['Gladiator', 'Braveheart', 'Rob Roy', '300'], 1);
$add('movie-quote', '"I see dead people."', ['The Others', 'The Sixth Sense', 'Stir of Echoes', 'The Ring'], 1);
$add('movie-quote', '"There\'s no place like home."', ['The Wizard of Oz', 'Alice in Wonderland', 'Mary Poppins', 'The Sound of Music'], 0);
$add('movie-quote', '"Say hello to my little friend!"', ['Goodfellas', 'Scarface', 'Carlito\'s Way', 'The Untouchables'], 1);
$add('movie-quote', '"My precious."', ['Harry Potter', 'The Hobbit', 'The Lord of the Rings', 'Willow'], 2);
$add('movie-quote', '"To infinity and beyond!"', ['Toy Story', 'WALL-E', 'The Incredibles', 'Monsters, Inc.'], 0);

/* ---------- movie trivia ---------- */
$add('movie-trivia', 'Which film won the first Academy Award for Best Picture (1929)?', ['Wings', 'Sunrise', 'The Jazz Singer', 'Metropolis'], 0);
$add('movie-trivia', 'Who directed "Jaws", "E.T." and "Jurassic Park"?', ['George Lucas', 'Steven Spielberg', 'Ridley Scott', 'James Cameron'], 1);
$add('movie-trivia', 'Which 1999 film features "the red pill and the blue pill"?', ['Fight Club', 'The Matrix', 'eXistenZ', 'Dark City'], 1);
$add('movie-trivia', 'What is the highest-grossing film franchise character played by Robert Downey Jr.?', ['Sherlock Holmes', 'Iron Man', 'Doctor Dolittle', 'Kirk'], 1);
$add('movie-trivia', 'The movie "Psycho" (1960) was directed by whom?', ['Alfred Hitchcock', 'Orson Welles', 'Billy Wilder', 'John Huston'], 0);
$add('movie-trivia', 'Which actor has played James Bond in the most official films?', ['Sean Connery', 'Roger Moore', 'Daniel Craig', 'Pierce Brosnan'], 1, 'Roger Moore: 7 films.');
$add('movie-trivia', '"Parasite" (2019), first non-English Best Picture winner, is from which country?', ['Japan', 'South Korea', 'China', 'Taiwan'], 1);
$add('movie-trivia', 'Pixar\'s first feature film was?', ['A Bug\'s Life', 'Toy Story', 'Monsters, Inc.', 'Finding Nemo'], 1);
$add('movie-trivia', 'Which film series is set in a galaxy "far, far away"?', ['Star Trek', 'Star Wars', 'Guardians of the Galaxy', 'Battlestar Galactica'], 1);
$add('movie-trivia', 'The character Vito Corleone appears in which film?', ['Scarface', 'Goodfellas', 'The Godfather', 'Once Upon a Time in America'], 2);
$add('movie-trivia', 'Which movie is famous for the line and scene "I\'m the king of the world!"?', ['Titanic', 'The Aviator', 'Master and Commander', 'Waterworld'], 0);
$add('movie-trivia', 'Andy Serkis provided motion capture for which Middle-earth character?', ['Sauron', 'Gollum', 'Treebeard', 'Elrond'], 1);

/* ---------- Duke Nukem one-liners ---------- */
$add('duke', 'Complete the Duke Nukem line: "Hail to the ___, baby!"', ['chief', 'king', 'boss', 'prez'], 1);
$add('duke', '"It\'s time to kick ass and chew bubble gum... and I\'m all ___ of gum."', ['out', 'sick', 'kinds', 'over it'], 0);
$add('duke', 'Which of these is a real Duke Nukem catchphrase?', ['Get over here', 'Come get some', 'Finish him', 'Stay a while'], 1);
$add('duke', 'Duke picks up a weapon and says: "___, baby!"', ['Sweet', 'Groovy', 'Radical', 'Excellent'], 1);
$add('duke', 'Looking in a mirror, Duke says: "Damn, I\'m looking ___!"', ['sharp', 'fine', 'good', 'buff'], 2);
$add('duke', 'Duke Nukem 3D was made by which studio?', ['id Software', '3D Realms', 'Epic MegaGames', 'Raven'], 1);
$add('duke', '"Nobody steals our chicks... and ___."', ['gets away', 'lives', 'walks', 'survives'], 1);
$add('duke', 'Duke stomps an alien and quips: "___ \'em out!"', ['Blow it', 'Chew', 'Knock', 'Take'], 0, 'From "Blow it out your ___".');
$add('duke', '"Let God sort \'em ___."', ['out', 'now', 'later', 'quick'], 0);
$add('duke', 'Which movie tough-guy franchise heavily inspired Duke\'s one-liners?', ['James Bond', 'Rambo / Terminator / Army of Darkness', 'John Wick', 'Mission: Impossible'], 1);
$add('duke', 'At an arcade machine Duke says he\'d rather play "___" than watch it.', ['Duke Nukem', 'a real game', 'this', 'pinball'], 0);
$add('duke', '"My name\'s Duke Nukem, and I\'m coming to get the rest of ___."', ['you', 'them', 'my money', 'the aliens'], 1);

/* ---------- emit SQL ---------- */
$esc = fn (string $s) => "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $s) . "'";
$vals = [];
foreach ($Q as [$cat, $q, $o, $a, $note]) {
    if (count($o) !== 4 || $a < 0 || $a > 3) { fwrite(STDERR, "BAD: $q\n"); exit(1); }
    $vals[] = '(' . $esc($cat) . ',' . $esc($q) . ',' . $esc($o[0]) . ',' . $esc($o[1]) . ',' . $esc($o[2]) . ',' . $esc($o[3]) . ',' . $a . ',' . $esc($note) . ')';
}
$sql = "-- Trivia / Quiz Bot question bank (bundled, no remote dependency).\n"
     . "CREATE TABLE IF NOT EXISTS trivia_questions (\n"
     . "  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,\n"
     . "  category VARCHAR(32) NOT NULL,\n"
     . "  question VARCHAR(400) NOT NULL,\n"
     . "  opt_a VARCHAR(200) NOT NULL,\n"
     . "  opt_b VARCHAR(200) NOT NULL,\n"
     . "  opt_c VARCHAR(200) NOT NULL,\n"
     . "  opt_d VARCHAR(200) NOT NULL,\n"
     . "  answer TINYINT NOT NULL,\n"
     . "  note VARCHAR(300) NOT NULL DEFAULT '',\n"
     . "  KEY category (category)\n"
     . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n"
     . "DELETE FROM trivia_questions;\n"
     . "INSERT INTO trivia_questions (category,question,opt_a,opt_b,opt_c,opt_d,answer,note) VALUES\n"
     . implode(",\n", $vals) . ";\n\n"
     . "INSERT INTO games (slug,name,description,module,score_label,score_order,enabled,sort) VALUES\n"
     . "  ('trivia','Trivia Bot','Multiple-choice quiz: Danish geo/politics/people, movies, fun facts','trivia','Correct','desc',1,145)\n"
     . "ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), module=VALUES(module),\n"
     . "  score_label=VALUES(score_label), score_order=VALUES(score_order), enabled=VALUES(enabled), sort=VALUES(sort);\n";

$path = '/var/www/vhosts-external/bbs.thugs.red/mysql/migrations/2026_09_05_10_trivia_bank.sql';
file_put_contents($path, $sql);
printf("wrote %s : %d questions across %d categories, %d bytes\n", $path, count($Q), count(array_unique(array_column($Q, 0))), strlen($sql));
foreach (array_count_values(array_column($Q, 0)) as $c => $n) echo "  $c: $n\n";
