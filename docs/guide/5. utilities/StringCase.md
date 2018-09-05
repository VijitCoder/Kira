# Утилиты. StringCase

Класс `\kira\utils\StringCase`

Преобразование строки между различными регистрами: CamelCase, snake_case, kebab-case:

__snakeToKebab__(string $str): _string_
__snakeToCamel__(string $str): _string_

__camelToSnake__(string $str): _string_
__camelToKebab__(string $str): _string_

__kebabToCamel__(string $str): _string_
__kebabToSnake__(string $str): _string_

Так же есть возможность привести произвольную строку к одному из перечисленных "регистров". При этом в ней заменяются не буквенно-числовые символов на пробел. Методы:

__toSnake__(string $str): _string_
__toKebab__(string $str): _string_
__toCamel__(string $str): _string_

В этом классе нет мультибайтной поддержки, все методы работают только с латиницей. Не стал ее реализовывать, т.к. на практике никогда не требовалось. В тоже время поддержка мультибайтных строк сложна и я не хочу тратить время на фичи, которые почти наверняка не нужны.


