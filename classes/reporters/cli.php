<?php

namespace mageekguy\atoum\reporters;

use \mageekguy\atoum;

class cli extends atoum\reporter
{
	protected $start = 0.0;
	protected $padding = 0;
	protected $currentMethod = '';
	protected $testMethods = 0;
	protected $testMethodNumber = 0;
	protected $progressBar = null;
	protected $score = null;

	public function runnerStart(atoum\runner $runner)
	{
		$this->score = new atoum\score();

		$this->start = microtime(true);

		self::write(sprintf($this->locale->_('Atoum version %s by %s.'), atoum\test::getVersion(), atoum\test::author));

		return $this;
	}

	public function testRunStart(atoum\test $test)
	{
		$this->progressBar = 0;
		$this->testMethods = 0;
		$this->testMethodNumber += sizeof($test);
		$this->progressBar = new atoum\reporters\cli\progressBar($test);

		self::write(sprintf($this->locale->_('Run %s...'), $test->getClass()));

		echo $this->progressBar;
	}

	public function beforeTestMethod(atoum\test $test)
	{
		$this->testMethods++;
		$this->currentMethod = $test->getCurrentMethod();
		return $this;
	}

	public function afterTestMethod(atoum\test $test)
	{
		$this->currentMethod = '';
		return $this;
	}

	public function testAssertionSuccess(atoum\test $test)
	{
		echo $this->progressBar->refresh('S');
		return $this;
	}

	public function testAssertionFail(atoum\test $test)
	{
		echo $this->progressBar->refresh('F');
		return $this;
	}

	public function testError(atoum\test $test)
	{
		echo $this->progressBar->refresh('e');
		return $this;
	}

	public function testException(atoum\test $test)
	{
		echo $this->progressBar->refresh('E');
		return $this;
	}

	public function testRunStop(atoum\test $test)
	{
		$score = $test->getScore();

		$testDuration = $score->getTotalDuration();

		self::write();
		self::write(sprintf($this->locale->__('Test duration: %4.2f second.', 'Duration: %4.2f seconds.', $testDuration), $testDuration));
		self::write(sprintf($this->locale->_('Memory usage: %4.2f Mb.'), $score->getTotalMemoryUsage() / 1048576));

		$this->score->merge($score);

		return $this;
	}

	public function runnerStop(atoum\runner $runner)
	{
		$runningDuration = microtime(true) - $this->start;

		$score = $this->score;

		$failNumber = $score->getFailNumber();
		$errorNumber = $score->getErrorNumber();
		$exceptionNumber = $score->getExceptionNumber();
		$outputNumber = $score->getOutputNumber();
		$testDuration = $score->getTotalDuration();

		if ($failNumber > 0)
		{
			self::write(sprintf($this->locale->_('Failure ! (%s, %s, %s)'), sprintf($this->locale->__('%d test', '%d tests', $this->testMethodNumber), $this->testMethodNumber), sprintf($this->locale->__('%d assertion', '%d assertions', $score->getAssertionNumber()), $score->getAssertionNumber()), sprintf($this->locale->__('%d failure', '%d failures', $failNumber), $failNumber)));
		}
		else
		{
			$problemNumber = $errorNumber + $exceptionNumber;

			if ($problemNumber === 0)
			{
				self::write(sprintf($this->locale->_('Success (%s, %s) !'), sprintf($this->locale->__('%d test', '%d tests', $this->testMethodNumber), $this->testMethodNumber), sprintf($this->locale->__('%d assertion', '%d assertions', $score->getAssertionNumber()), $score->getAssertionNumber())));
			}
			else
			{
				self::write(sprintf($this->locale->__('Success (%s, %s), but there is %s problem...', 'Success (%s, %s), but there are %s problems...', $problemNumber), sprintf($this->locale->__('%d test', '%d tests', $this->testMethodNumber), $this->testMethodNumber), sprintf($this->locale->__('%d assertion', '%d assertions', $score->getAssertionNumber()), $score->getAssertionNumber()), $problemNumber));
			}
		}

		if ($outputNumber > 0)
		{
			self::write($this->locale->_('Output:'));

			foreach ($score->getOutputs() as $output)
			{
				self::write($output['class'] . '::' . $output['method'] . '():', 1);

				foreach (explode("\n", trim($output['value'])) as $line)
				{
					self::write($line, 2);
				}
			}
		}

		if ($failNumber > 0)
		{
			self::write(sprintf($this->locale->__('There is %d failure', 'There are %d failures', $failNumber), $failNumber) . ':');

			foreach ($score->getFailAssertions() as $assertion)
			{
				self::write($assertion['class'] . '::' . $assertion['method'] . '():', 1);
				self::write(sprintf('%s failed because %s in file %s at line %d', $assertion['asserter'], $assertion['fail'], $assertion['file'], $assertion['line']), 2);
			}
		}

		if ($errorNumber > 0)
		{
			self::write(sprintf($this->locale->__('There is %d error', 'There are %d errors', $errorNumber), $errorNumber) . ':');

			$class = null;
			$method = null;

			foreach ($score->getErrors() as $error)
			{
				if ($error['class'] != $class || $error['method'] != $method)
				{
					$class = $error['class'];
					$method = $error['method'];
					self::write($error['class'] . '::' . $error['method'] . '():', 1);
				}

				self::write(sprintf($this->locale->_('Error %s:'), self::getErrorLabel($error['type'])), 2);
				self::write($error['message'], 3);
			}
		}

		if ($exceptionNumber > 0)
		{
			self::write(sprintf($this->locale->__('There is %d exception', 'There are %d exceptions', $exceptionNumber), $exceptionNumber) . ':');

			foreach ($score->getExceptions() as $exception)
			{
				self::write($exception['class'] . '::' . $exception['method'] . '():', 1);

				foreach (explode("\n", $exception['value']) as $line)
				{
					self::write($line, 2);
				}
			}
		}

		self::write(sprintf($this->locale->__('Total test duration: %4.2f second.', 'Duration: %4.2f seconds.', $testDuration), $testDuration));
		self::write(sprintf($this->locale->_('Total test memory usage: %4.2f Mb.'), $score->getTotalMemoryUsage() / 1048576));
		self::write(sprintf($this->locale->__('Running duration: %4.2f second.', 'Running duration: %4.2f seconds.', $runningDuration), $runningDuration));

		return $this;
	}

	public static function write($message = '', $level = 0)
	{
		$messages = explode("\n", $message);

		foreach ($messages as $message)
		{
			echo ($level <= 0 ? '' : str_repeat('   ', $level)) . rtrim($message) . PHP_EOL;
		}
	}
}

?>
