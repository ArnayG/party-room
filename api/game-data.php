<?php
require_once __DIR__.'/prompt-bank.php';
// Original prompt sets for this web adaptation.
function party_spectrums(): array {
    return array_merge([
        ['Cold','Hot'],['Underrated','Overrated'],['Cheap','Expensive'],['Ordinary','Extraordinary'],
        ['Quiet','Loud'],['Needs a plan','Better spontaneous'],['Snack','Meal'],['Relaxing','Stressful'],
        ['Old school','Futuristic'],['Easy to learn','Hard to master'],['Bad superpower','Great superpower'],
        ['Forgettable','Iconic'],['Tame','Chaotic'],['Useful','Useless'],['Local','Worldwide'],
        ['Tiny inconvenience','Total disaster'],['Private','Public'],['Casual','Fancy'],['Safe','Risky'],
        ['Guilty pleasure','Proud favorite'],['Early bird','Night owl'],['Made for kids','Made for adults'],
        ['Small talk','Deep conversation'],['A need','A want'],['Better alone','Better together'],
        ['Short-lived','Timeless'],['Clean','Messy'],['Slow','Fast'],['Predictable','Surprising'],
        ['Beginner friendly','Expert only'],['Bad first date','Great first date'],['Undramatic','Dramatic'],
        ['Forgettable smell','Unmistakable smell'],['Clever','Ridiculous'],['Indoor','Outdoor'],
        ['Serious','Silly'],['Practical gift','Thoughtful gift'],['Simple','Complicated'],
        ['Everyday thing','Special occasion'],['Underwhelming','Mind-blowing'],['Soft','Hard'],
        ['Sweet','Savory'],['Smooth','Awkward'],['Good idea','Terrible idea'],['Easy to replace','Irreplaceable'],
        ['Nobody knows it','Everybody knows it'],['Slow burn','Instant obsession'],['Trashy','Classy'],
        ['Tiny flex','Huge flex'],['Sensible outfit','Wild outfit'],['Believable','Unbelievable'],
        ['A little annoying','Unbearable'],['Deeply uncool','Effortlessly cool'],['Formal','Informal'],
        ['Nothing to pack','Pack everything'],['Background character','Main character'],
        ['An acquired taste','Love at first bite'],['Small win','Big victory'],['Easy to explain','Impossible to explain'],
        ['Bad song to dance to','Perfect song to dance to'],['Forgotten hobby','Lifelong hobby'],
        ['Throw it away','Keep it forever'],['Minimal effort','Maximum effort'],['Unlucky','Lucky']
    ],party_extra_spectrums());
}
function party_words(): array {
    $base = [
        'Food'=>['Pizza','Sushi','Pancakes','Popcorn','Avocado','Chocolate','Spaghetti','Tacos','Croissant','Waffles','Pickles','Mango','Cheesecake','Burrito','Pretzel','Cereal','Dumplings','Lasagna','Marshmallow','Donut','Watermelon','Coconut','Omelette','Nachos','Ramen','Ginger','Pineapple','Lemon','Garlic','Honey'],
        'Places'=>['Airport','Library','Beach','Museum','Hospital','Stadium','Aquarium','Cinema','Supermarket','Playground','Lighthouse','Bakery','Subway','Castle','Campsite','Casino','Barbershop','Zoo','Restaurant','Waterpark','School','Mountain','Desert','Harbor','Farm','Rooftop','Jungle','Volcano','Garage','Bowling alley'],
        'Objects'=>['Umbrella','Toothbrush','Backpack','Headphones','Microwave','Skateboard','Telescope','Candle','Scissors','Guitar','Pillow','Camera','Hammer','Mirror','Compass','Bicycle','Suitcase','Keyboard','Flashlight','Sunglasses','Alarm clock','Paintbrush','Snow globe','Stapler','Blanket','Remote','Toaster','Helmet','Printer','Calculator'],
        'Animals'=>['Penguin','Giraffe','Octopus','Dolphin','Kangaroo','Elephant','Butterfly','Crocodile','Peacock','Hedgehog','Flamingo','Gorilla','Chameleon','Raccoon','Seahorse','Jellyfish','Koala','Hamster','Owl','Lobster','Panda','Fox','Tiger','Sloth','Camel','Whale','Dragonfly','Rabbit','Parrot','Turtle'],
        'Activities'=>['Camping','Karaoke','Surfing','Painting','Baking','Yoga','Fishing','Gardening','Bowling','Hiking','Shopping','Swimming','Dancing','Skateboarding','Chess','Photography','Juggling','Skiing','Wrestling','Meditation','Podcasting','Running','Knitting','Climbing','Tennis','Diving','Acting','Archery','Cooking','Sailing'],
        'Entertainment'=>['Superhero','Documentary','Cartoon','Sitcom','Magic show','Concert','Video game','Reality show','Stand-up','Music video','Thriller','Musical','Romance','Animation','Podcast','Opera','Ballet','Circus','Talent show','Festival','Arcade','Puzzle','Board game','Movie trailer','Soundtrack','Comic book','Fairy tale','Mystery','Science fiction','Horror']
    ];
    foreach(party_extra_words() as $category=>$words)$base[$category]=array_values(array_unique(array_merge($base[$category]??[],$words)));
    return $base;
}
