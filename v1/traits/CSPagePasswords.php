<?php

class CSPagePasswords {

	private
		$parent = null;

	public
		$common_passwords = array('0', '1111', '1212', '1234', '1313', '11111', '12345', '111111', '112233', '121212', '123123', '123321', '123456', '131313', '1234567', '11111111', '12345678', '123456789', '1234567890', '123qwe', '18atcskd2w', '1q2w3e', '1q2w3e4r', '1q2w3e4r5t', '2000', '2112', '2222', '3333', '3rjs1la7qe', '4128', '4321', '4444', '5150', '5555', '6666', '6969', '7777', '232323', '555555', '654321', '666666', '696969', '777777', '987654', '7777777', '8675309', '987654321', 'FRIENDS', 'aaaa', 'aaaaaa', 'abc123', 'abgrtyu', 'access', 'access14', 'action', 'albert', 'alex', 'alexis', 'amanda', 'amateur', 'andrea', 'andrew', 'angel', 'angela', 'angels', 'animal', 'anthony', 'apollo', 'apple', 'apples', 'arsenal', 'arthur', 'asdf', 'asdfgh', 'ashley', 'asshole', 'august', 'austin', 'baby', 'babygirl', 'badboy', 'bailey', 'banana', 'barney', 'baseball', 'batman', 'beach', 'bear', 'beaver', 'beavis', 'beer', 'bigcock', 'bigdaddy', 'bigdick', 'bigdog', 'bigtits', 'bill', 'billy', 'birdie', 'bitch', 'bitches', 'biteme', 'black', 'blazer', 'blonde', 'blondes', 'blowjob', 'blowme', 'blue', 'bond007', 'bonnie', 'booboo', 'boobs', 'booger', 'boomer', 'booty', 'boston', 'brandon', 'brandy', 'braves', 'brazil', 'brian', 'bronco', 'broncos', 'bubba', 'buddy', 'bulldog', 'buster', 'butter', 'butthead', 'calvin', 'camaro', 'cameron', 'canada', 'captain', 'carlos', 'carter', 'casper', 'charles', 'charlie', 'cheese', 'chelsea', 'chester', 'chevy', 'chicago', 'chicken', 'chocolate', 'chris', 'cocacola', 'cock', 'coffee', 'college', 'compaq', 'computer', 'cookie', 'cool', 'cooper', 'corvette', 'cowboy', 'cowboys', 'cream', 'crystal', 'cumming', 'cumshot', 'cunt', 'dakota', 'dallas', 'daniel', 'danielle', 'dave', 'david', 'debbie', 'dennis', 'diablo', 'diamond', 'dick', 'dirty', 'doctor', 'doggie', 'dolphin', 'dolphins', 'donald', 'dragon', 'dreams', 'driver', 'eagle', 'eagle1', 'eagles', 'edward', 'einstein', 'enjoy', 'enter', 'eric', 'erotic', 'extreme', 'falcon', 'fender', 'ferrari', 'fire', 'firebird', 'fish', 'fishing', 'florida', 'flower', 'flyers', 'football', 'ford', 'forever', 'frank', 'fred', 'freddy', 'freedom', 'fuck', 'fucked', 'fucker', 'fucking', 'fuckme', 'fuckyou', 'gandalf', 'gateway', 'gators', 'gemini', 'george', 'giants', 'ginger', 'girl', 'girls', 'golden', 'golf', 'golfer', 'google', 'gordon', 'great', 'green', 'gregory', 'guitar', 'gunner', 'hammer', 'hannah', 'happy', 'hardcore', 'harley', 'heather', 'hello', 'helpme', 'hentai', 'hockey', 'hooters', 'horney', 'horny', 'hotdog', 'house', 'hunter', 'hunting', 'iceman', 'iloveu', 'iloveyou', 'internet', 'iwantu', 'jack', 'jackie', 'jackson', 'jaguar', 'jake', 'james', 'japan', 'jasmine', 'jason', 'jasper', 'jennifer', 'jeremy', 'jessica', 'john', 'johnny', 'johnson', 'jordan', 'joseph', 'joshua', 'juice', 'junior', 'justin', 'kelly', 'kevin', 'killer', 'king', 'kitty', 'knight', 'ladies', 'lakers', 'lauren', 'leather', 'legend', 'letmein', 'little', 'london', 'love', 'lovely', 'lover', 'lovers', 'lucky', 'maddog', 'madison', 'maggie', 'magic', 'magnum', 'marine', 'mark', 'marlboro', 'martin', 'marvin', 'master', 'matrix', 'matt', 'matthew', 'maverick', 'maxwell', 'melissa', 'member', 'mercedes', 'merlin', 'michael', 'michelle', 'mickey', 'midnight', 'mike', 'miller', 'mine', 'mistress', 'money', 'monica', 'monkey', 'monster', 'morgan', 'mother', 'mountain', 'movie', 'muffin', 'murphy', 'music', 'mustang', 'mynoob', 'naked', 'nascar', 'nathan', 'naughty', 'ncc1701', 'newyork', 'nicholas', 'nicole', 'nipple', 'nipples', 'oliver', 'orange', 'ou812', 'packers', 'panther', 'panties', 'paris', 'parker', 'pass', 'password', 'password1', 'patrick', 'paul', 'peaches', 'peanut', 'penis', 'pepper', 'peter', 'phantom', 'phoenix', 'player', 'please', 'pookie', 'porn', 'porno', 'porsche', 'power', 'prince', 'princess', 'private', 'purple', 'pussies', 'pussy', 'qazwsx', 'qwert', 'qwerty', 'qwertyui', 'qwertyuiop', 'rabbit', 'rachel', 'racing', 'raiders', 'rainbow', 'ranger', 'rangers', 'rebecca', 'redskins', 'redsox', 'redwings', 'richard', 'robert', 'rock', 'rocket', 'rockyou', 'rosebud', 'runner', 'rush2112', 'russia', 'samantha', 'sammy', 'samson', 'sandra', 'saturn', 'scooby', 'scooter', 'scorpio', 'scorpion', 'scott', 'secret', 'sexsex', 'sexy', 'shadow', 'shannon', 'shaved', 'shit', 'sierra', 'silver', 'skippy', 'slayer', 'slut', 'smith', 'smokey', 'snoopy', 'soccer', 'sophie', 'spanky', 'sparky', 'spider', 'squirt', 'srinivas', 'star', 'stars', 'startrek', 'starwars', 'steelers', 'steve', 'steven', 'sticky', 'stupid', 'success', 'suckit', 'summer', 'sunshine', 'super', 'superman', 'surfer', 'swimming', 'sydney', 'taylor', 'teens', 'tennis', 'teresa', 'test', 'tester', 'testing', 'theman', 'thomas', 'thunder', 'thx1138', 'tiffany', 'tiger', 'tigers', 'tigger', 'time', 'tits', 'tomcat', 'topgun', 'toyota', 'travis', 'trouble', 'trustno1', 'tucker', 'turtle', 'united', 'vagina', 'victor', 'victoria', 'video', 'viking', 'viper', 'voodoo', 'voyager', 'walter', 'warrior', 'welcome', 'whatever', 'white', 'william', 'willie', 'wilson', 'winner', 'winston', 'winter', 'wizard', 'wolf', 'women', 'xavier', 'xxxx', 'xxxxx', 'xxxxxx', 'xxxxxxxx', 'yamaha', 'yankee', 'yankees', 'yellow', 'young', 'zxcvbn', 'zxcvbnm', 'zzzzzz');

	// Check if a password is common.
	public function isCommon($md5) {
		$count_cp = count($common_passwords);
		for ($x = 0; $x < $count_cp; $x++) {
			if ($md5 == md5($common_passwords[$x]))
				return true;
		}
		return false;
	}

	// Convert text file to PHP array.
	public function txt2php() {

		// Get passwords from file.
		$common_passwords = array_map(
			function($common_password) {
				return '\'' . str_replace('\'', '\\\'', $common_password) . '\'';
			},
			array_map('rtrim', file('CSPagePasswords.txt'))
		);

		return '$' . 'common_passwords = array(' . implode(', ', $common_passwords) . ');';
	}

}

?>