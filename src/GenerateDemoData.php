<?php

namespace BukuMasjid\DemoData;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateDemoData extends Command
{
    protected $signature = 'buku-masjid:generate-demo-data
                            {--reset-all : Reset seluruh isi database}
                            ';

    protected $description = 'Generate data demo untuk simulasi.';

    public function handle()
    {
        $confirm = $this->confirm('Anda yakin ini generate data demo?');
        if ($confirm == false) {
            return;
        }

        if ($this->option('reset-all')) {
            $confirm = $this->confirm('Kosongkan seluruh isi database?');
            if ($confirm) {
                $this->call('migrate:fresh', ['--seed' => true]);
            }
        }

        $this->generateBooks();
        $this->generateBankAccounts();
        $this->generateBankAccountBalances();
        $this->generateLecturingSchedulesWithTransactions();
        $this->generateTransactions();

        $this->info('Demo data sudah digenerate!');
    }

    public function generateBooks()
    {
        $this->comment(date('Y-m-d H:i:s').' Start generate Books...');
        DB::table('books')->insert([
            ['name' => 'Ramadhan 2022', 'description' => 'Buku catatan keuangan Ramadhan 2022', 'creator_id' => 1],
            ['name' => 'Qurban 2022', 'description' => 'Buku catatan keuangan Qurban 2022', 'creator_id' => 1],
            ['name' => 'Ramadhan 2023', 'description' => 'Buku catatan keuangan Ramadhan 2023', 'creator_id' => 1],
            ['name' => 'Qurban 2023', 'description' => 'Buku catatan keuangan Qurban 2023', 'creator_id' => 1],
        ]);
        $this->comment(date('Y-m-d H:i:s').' Finish generate Books');
    }

    public function generateBankAccounts()
    {
        $this->comment(date('Y-m-d H:i:s').' Start generate Bank Accounts...');
        $bankAccountId = DB::table('bank_accounts')->insertGetId([
            'name' => 'BSI Operasional Masjid',
            'number' => '0123456789',
            'account_name' => 'Masjid As-Salam',
            'creator_id' => 1,
        ]);
        DB::table('books')->where('id', 1)->update(['bank_account_id' => $bankAccountId]);
        $this->comment(date('Y-m-d H:i:s').' Finish generate Bank Accounts');
    }

    public function generateBankAccountBalances()
    {
        $this->comment(date('Y-m-d H:i:s').' Start generate Bank Account Balances...');
        $firstBankAccount = DB::table('bank_accounts')->latest('id')->first();
        DB::table('bank_account_balances')->insert([
            ['bank_account_id' => $firstBankAccount->id, 'date' => now()->subMonths(4)->format('Y-m-t'), 'amount' => 34568400, 'description' => 'Saldo akhir '.now()->subMonths(4)->isoFormat('MMMM Y'), 'creator_id' => 1],
            ['bank_account_id' => $firstBankAccount->id, 'date' => now()->subMonths(3)->format('Y-m-t'), 'amount' => 39268400, 'description' => 'Saldo akhir '.now()->subMonths(3)->isoFormat('MMMM Y'), 'creator_id' => 1],
            ['bank_account_id' => $firstBankAccount->id, 'date' => now()->subMonths(2)->format('Y-m-t'), 'amount' => 49568400, 'description' => 'Saldo akhir '.now()->subMonths(2)->isoFormat('MMMM Y'), 'creator_id' => 1],
            ['bank_account_id' => $firstBankAccount->id, 'date' => now()->subMonths(1)->format('Y-m-t'), 'amount' => 53297160, 'description' => 'Saldo akhir '.now()->subMonths(1)->isoFormat('MMMM Y'), 'creator_id' => 1],
        ]);
        $this->comment(date('Y-m-d H:i:s').' Finish generate Bank Account Balances');
    }

    public function generateLecturingSchedulesWithTransactions()
    {
        $this->comment(date('Y-m-d H:i:s').' Start generate Lecturing Schedules...');
        $dateRange = $this->getDateRange();
        foreach ($dateRange as $date) {
            $this->generateLecturingScheduleWithTransactions($date);
        }
        $this->comment(date('Y-m-d H:i:s').' Finish generate Lecturing Schedules');
    }

    private function getDateRange(): array
    {
        // Ref: https://stackoverflow.com/a/4312630
        $dateRange = [];
        $period = new \DatePeriod(
            Carbon::parse(now()->subMonths(2)->format('Y-m').'-01'),
            new \DateInterval('P1D'),
            Carbon::parse(now()->addMonth()->format('Y-m-t'))
        );
        foreach ($period as $date) {
            $dateRange[] = $date;
        }
        return $dateRange;
    }

    private function generateLecturingScheduleWithTransactions(Carbon $date): void
    {
        $dayName = $date->locale('en_EN')->dayName;
        $generatorClassNamespace = 'BukuMasjid\DemoData\Lecturings\\';
        $generatorClassName = $generatorClassNamespace.$dayName.'LecturingGenerator';
        if (class_exists($generatorClassName)) {
            (new $generatorClassName)->generate($date);
        }
        if ($date->lessThanOrEqualTo(today())) {
            $generatorClassName = $generatorClassNamespace.$dayName.'LecturingTransactionsGenerator';
            if (class_exists($generatorClassName)) {
                (new $generatorClassName)->generate($date);
            }
        }
    }

    public function generateTransactions()
    {
        $this->comment(date('Y-m-d H:i:s').' Start generate Transactions...');
        foreach ($this->getDateRange() as $date) {
            if ($date->greaterThan(today())) {
                break;
            }
            $this->generateBillPaymentTransactions($date);
            $this->generateSalaryTransactions($date);
        }
        $this->comment(date('Y-m-d H:i:s').' Finish generate Transactions');
    }

    private function generateBillPaymentTransactions(Carbon $date)
    {
        $firstTuesdayOfTheMonth = Carbon::parse('first tuesday of '.$date->format('F Y'));
        if (!$date->equalTo($firstTuesdayOfTheMonth)) {
            return;
        }

        $electricBillCategory = DB::table('categories')->where('name', 'Tagihan Listrik')->first();
        DB::table('transactions')->insert([
            'date' => $date->format('Y-m-d'),
            'category_id' => $electricBillCategory->id,
            'amount' => 5496000,
            'description' => 'Bayar tagihan listrik '.$date->isoFormat('MMMM Y'),
            'in_out' => 0,
            'book_id' => 1,
            'creator_id' => 1,
        ]);
        $waterBillCategory = DB::table('categories')->where('name', 'Tagihan Air')->first();
        DB::table('transactions')->insert([
            'date' => $date->format('Y-m-d'),
            'category_id' => $waterBillCategory->id,
            'amount' => 757200,
            'description' => 'Bayar tagihan PDAM '.$date->isoFormat('MMMM Y'),
            'in_out' => 0,
            'book_id' => 1,
            'creator_id' => 1,
        ]);
        $internetBillCategory = DB::table('categories')->where('name', 'Tagihan Air')->first();
        DB::table('transactions')->insert([
            'date' => $date->format('Y-m-d'),
            'category_id' => $internetBillCategory->id,
            'amount' => 431000,
            'description' => 'Bayar tagihan Internet '.$date->isoFormat('MMMM Y'),
            'in_out' => 0,
            'book_id' => 1,
            'creator_id' => 1,
        ]);
    }

    private function generateSalaryTransactions(Carbon $date)
    {
        $lastDayOfTheMonthDate = Carbon::parse($date->format('Y-m-t'));
        if (!$date->equalTo($lastDayOfTheMonthDate)) {
            return;
        }

        $salaryCategory = DB::table('categories')->where('name', 'Gaji Karyawan')->first();
        DB::table('transactions')->insert([
            'date' => $date->format('Y-m-d'),
            'category_id' => $salaryCategory->id,
            'amount' => 2125000,
            'description' => 'Insentif Fulan (Satpam)',
            'in_out' => 0,
            'book_id' => 1,
            'creator_id' => 1,
        ]);
        DB::table('transactions')->insert([
            'date' => $date->format('Y-m-d'),
            'category_id' => $salaryCategory->id,
            'amount' => 1000000,
            'description' => 'Gaji Fulan',
            'in_out' => 0,
            'book_id' => 1,
            'creator_id' => 1,
        ]);
        DB::table('transactions')->insert([
            'date' => $date->format('Y-m-d'),
            'category_id' => $salaryCategory->id,
            'amount' => 750000,
            'description' => 'Insentif Admin Fulan',
            'in_out' => 0,
            'book_id' => 1,
            'creator_id' => 1,
        ]);
        DB::table('transactions')->insert([
            'date' => $date->format('Y-m-d'),
            'category_id' => $salaryCategory->id,
            'amount' => 275000,
            'description' => 'Insentif Fulan',
            'in_out' => 0,
            'book_id' => 1,
            'creator_id' => 1,
        ]);
    }
}
