<?php

namespace Tests\Unit;

use App\Console\Commands\BirthdayCakeDaysCommand;
use App\Http\Controllers\BirthdayCakeDaysController;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class OutputBirthdayCakeDaysTest extends TestCase
{
    /**
     * Ensure command exists
     *
     * @return void
     */
    public function test_output_birthday_cake_command_class_exists()
    {
        $this->assertTrue( class_exists( BirthdayCakeDaysCommand::class ) );
    }

    /**
     * Ensure argument file is required to execute the command
     *
     * @return void
     */
    public function test_argument_file_is_required()
    {
        $controller = new BirthdayCakeDaysController();
        $vfs = vfsStream::setup( 'root' ); //create empty directory
        $this->expectException( \Exception::class );
        $controller->isValidFile( $vfs->url() );
    }

    /** Ensure argument file exists in the location mentioned
     *
     * @return void
     */
    public function test_argument_file_exists_in_location()
    {
        $controller = new BirthdayCakeDaysController();
        $vfs = vfsStream::setup( 'root' ); //create empty directory
        $this->expectException( \Exception::class );
        $controller->isValidFile( $vfs->url() . '/TestData.txt' );
    }

    /**
     * Ensure argument file is of allowed extensions (csv/txt)
     *
     * @return void
     */
    public function test_argument_file_is_of_allowed_extensions()
    {
        $controller = new BirthdayCakeDaysController();
        $vfs = vfsStream::setup( 'root' ); //create empty directory
        $this->expectException( \Exception::class );
        $controller->isValidFile( $vfs->url() . '/TestData.doc' );
    }

    /**
     * Ensure input data is of expected format (Name,yyyy-mm-dd)
     *
     * @return void
     */
    public function test_argument_file_has_data_in_expected_format()
    {
        $controller = new BirthdayCakeDaysController();
        $data = [
            'invalidData.txt' =>
                '2004-01-01,Steve
                2001-07-01,Laura'
        ];

        $vfs = vfsStream::setup( 'root', null, $data );

        $this->expectException( \Exception::class );
        $controller->fetchEmployeeBirthday( $vfs->url() . '/invalidData.txt' );
    }

    /**
     * Ensure DOB is in expected format (yyyy-mm-dd)
     *
     * @return void
     */
    public function test_argument_file_has_dob_in_expected_format()
    {
        $controller = new BirthdayCakeDaysController();
        $data = [
            'invalidDate.txt' =>
                'Steve,0000/00/00
                 Laura,0000/00/00'
        ];

        $vfs = vfsStream::setup( 'root', null, $data );

        $this->expectException( \Exception::class );
        $controller->fetchEmployeeBirthday( $vfs->url() . '/invalidDate.txt' );
    }

    /**
     * Ensure weekends and company holidays are skipped from cake days
     *
     * @return void
     */
    public function test_company_holidays_and_weekends_are_skipped()
    {
        $controller = new BirthdayCakeDaysController();
        $data = [
            'employeeBdy.txt' =>
                'Steve,1997-01-01
                 Maria,1987-09-24
                 Bernard,1973-02-06'
        ];

        $vfs = vfsStream::setup( 'root', null, $data );

        $employeeBirthday = $controller->fetchEmployeeBirthday( $vfs->url() . '/employeeBdy.txt' );
        $employeeCakeDay = $controller->setEmployeeCakeDay( $employeeBirthday );

        // verify return array doesn't have company holidays or weekends included
        $this->assertNotContains( '01-01', $employeeCakeDay );
        $this->assertNotContains( '02-06', $employeeCakeDay );
    }

    /**
     * Ensure birthday is skipped and next working day is taken into account
     *
     * @return void
     */
    public function test_cake_day_is_next_working_day_after_birthday()
    {
        $controller = new BirthdayCakeDaysController();
        $data = [
            'employeeBdy.txt' =>
                'Steve,1997-03-16
                 Maria,1987-09-24
                 Bernard,1973-02-10'
        ];

        $vfs = vfsStream::setup( 'root', null, $data );

        $employeeBirthday = $controller->fetchEmployeeBirthday( $vfs->url() . '/employeeBdy.txt' );
        $employeeDayOff = $controller->skipEmployeeDayOff( $employeeBirthday );

        // verify return array doesn't have birthday days
        $this->assertNotContains( '03-16', $employeeDayOff );
        $this->assertNotContains( '09-24', $employeeDayOff );
        $this->assertNotContains( '02-10', $employeeDayOff );
    }

    public function test_no_cake_on_cake_free_day_for_health_reasons()
    {
        $controller = new BirthdayCakeDaysController();
        $data = [
            'employeeBdy.txt' =>
                'Steve,2000-07-21
                Laura,1964-07-22'
        ];

        $vfs = vfsStream::setup( 'root', null, $data );

        $employeeBirthday = $controller->fetchEmployeeBirthday( $vfs->url() . '/employeeBdy.txt' );
        $nextCakeDay = $controller->setEmployeeCakeDay( $employeeBirthday );
        $skipEmployeeDayOff = $controller->skipEmployeeDayOff( $nextCakeDay );
        $fetchNextWorkingDay = $controller->fetchNextWorkingDay( $skipEmployeeDayOff );
        $fetchCakeDays = $controller->fetchCakeDays( $fetchNextWorkingDay );

        $this->assertNotContains( '07-23', $fetchCakeDays );
    }

    public function test_large_cake_replaced_with_more_than_one_small_cake()
    {
        $controller = new BirthdayCakeDaysController();
        $data = [
            'employeeBdy.txt' =>
                'Steve,1977-07-21
                Laura,1977-07-21'
        ];

        $vfs = vfsStream::setup( 'root', null, $data );

        $employeeBirthday = $controller->fetchEmployeeBirthday( $vfs->url() . '/employeeBdy.txt' );
        $nextCakeDay = $controller->setEmployeeCakeDay( $employeeBirthday );
        $skipEmployeeDayOff = $controller->skipEmployeeDayOff( $nextCakeDay );
        $fetchNextWorkingDay = $controller->fetchNextWorkingDay( $skipEmployeeDayOff );
        $fetchCakeDays = $controller->fetchCakeDays( $fetchNextWorkingDay );

        $cakes = $fetchCakeDays[0]['cakes'];

        $this->assertGreaterThan('1', $cakes);
    }
}
