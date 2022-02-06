
# Employee Birthday Cake Days

A small command-line utility which helps track a company's employee names, birthday cakes that were sent over (large and/or small) and the date on which the cake was sent.


* The utility should receive an input text file containing the names of employees and their DOBs in the following format with one entry per line:

       [Name],[DOB (yyyy-mm-dd format)]

       For example:

       Steve,1992-10-14
       Mary,1989-06-21



* The utility should output a CSV file detailing the dates we have cake for the current year, in the following format:

        'Date', 'Number of Small Cakes', 'Number of Large Cakes', 'Names of people getting cakes'


Steps to follow:

* Download and unzip `employee-birthday-cake-app`
* `cd` into the unzipped folder  
* Run `composer install`
* Create a file with valid test data (.txt or .csv format)
* Run the artisan command in terminal using `php artisan output:birthday_cake_days TestData.txt`

Rules:

“Cake Days” are calculated according to the following rules: 

* A small cake is provided on the employee’s first working day after their birthday.

* All employees get their birthday off. 
* The office is closed on weekends, Christmas Day, Boxing Day and New Year’s Day.
* If the office is closed on an employee’s birthday, they get the next working day off.
* If two or more cakes days coincide, we instead provide one large cake to share. 
* If there is to be cake two days in a row, we instead provide one large cake on the second day. 
* For health reasons, the day after each cake must be cake-free. Any cakes due on a cake-free day  are postponed to the next working day. 
* There is never more than one cake a day. 







