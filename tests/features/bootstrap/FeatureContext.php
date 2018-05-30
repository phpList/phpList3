<?php
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Selector\Xpath\Escaper;
use WebDriver\Element;
use \Behat\Mink\Element\NodeElement;
use WebDriver\Exception\NoSuchElement;
use WebDriver\Exception\UnknownCommand;
use WebDriver\Exception\UnknownError;
use WebDriver\Exception;
use WebDriver\Key;
use WebDriver\WebDriver;
use WebDriver\Exception\UnexpectedAlertOpen;

use Behat\Behat\Context\Context;

use Behat\Mink\Exception\ExpectationException;
use Behat\MinkExtension\Context\MinkContext;
use Behat\MinkExtension\Context\RawMinkContext;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ResponseTextException;
use Behat\Mink\Exception\ElementNotFoundException;
use WebDriver\Exception\StaleElementReference;
use Behat\Behat\Tester\Exception\PendingException;


//
// Require 3rd-party libraries here:
//
//   require_once 'PHPUnit/Autoload.php';
//   require_once 'PHPUnit/Framework/Assert/Functions.php';
//

/**
 * Features context.
 */

class FeatureContext extends MinkContext
{
    private $params = array();
    private $data = array();
    private $db;
    /**
     * Initializes context.
     * Every scenario gets its own context object.
     *
     * @param array $admin
     * @param array $database
     */
    public function __construct( $database = array(), $admin = array())
    {
        // merge default database value into configured value
        $database = array_merge(array(
            'host'      => 'localhost',
            'password'  => 'phplist',
            'user'      => 'phplist',
            'name'      => 'phplistdb'
        ),$database);
        // merge default admin user value into configured value
        $admin = array_merge(array(
            'username' => 'admin',
            'password' => 'admin'
        ),$admin);
        $this->params = array(
            'db_host' => $database['host'],
            'db_user' => $database['user'],
            'db_password' => $database['password'],
            'db_name' => $database['name'],
            'admin_username' => $admin['username'],
            'admin_password' => $admin['password']
        );
        
        $this->db = mysqli_init();
        mysqli_real_connect(
            $this->db,
            $database['host'],
            $database['user'],
            $database['password'],
            $database['name']
        );
    }
    public function __call($method, $parameters)
    {
        // we try to call the method on the Page first
        $page = $this->getSession()->getPage();
        if (method_exists($page, $method)) {
            return call_user_func_array(array($page, $method), $parameters);
        }
        // we try to call the method on the Session
        $session = $this->getSession();
        if (method_exists($session, $method)) {
            return call_user_func_array(array($session, $method), $parameters);
        }
        // could not find the method at all
        throw new \RuntimeException(sprintf(
            'The "%s()" method does not exist.', $method
        ));
    }
    /**
     * Everyone who tried Behat with Mink and a JavaScript driver (I use 
     * Selenium2Driver with phantomjs) has had issues with trying to assert something 
     * in the current web page while some JavaScript code has not been finished yet 
     * (pending Ajax query for example).
     * 
     * The proper and recommended way of dealing with these issues is to use a spin 
     * method in your context, that will run the assertion or code multiple times 
     * before failing. Here is my implementation that you can add to your BaseContext:
     */
    public function spins($closure, $tries = 10)
    {
        for ($i = 0; $i <= $tries; $i++) {
            try {
                $closure();
                return;
            } catch (\Exception $e) {
                if ($i == $tries) {
                    throw $e;
                }
            }
            sleep(1);
        }
    }
    
    // Output page contents in case of failure
    // TODO: extend docs
    protected function throwExpectationException($message)
    {
        throw new ExpectationException($message, $this->getSession());
    }
    /**
     * @When something long is taking long but should output :text
     */
    public function somethingLongShouldOutput($text)
    {
        $this->find('css', 'button#longStuff')->click();
        $this->spins(function() use ($text) { 
            $this->assertSession()->pageTextContains($text);
        });
    }
    /**
     * @Then do something on a button that might not be there yet
     */
    public function doSomethingNotThereYet()
    {
        $this->spins(function() { 
            $button = $this->find('css', 'button#mightNotBeThereYet');
            if (!$button) {
                throw \Exception('Button is not there yet :(');
            }
            $button->click();
        });
    }
//
// Place your definition and hook methods here:
//
//    /**
//     * @Given /^I have done something with "([^"]*)"$/
//     */
//    public function iHaveDoneSomethingWith($argument)
//    {
//        doSomethingWith($argument);
//    }
//
    /**
     * @When /^I recreate the database$/
     */
    public function iRecreateTheDatabase()
    {
        mysqli_query($this->db,'drop database if exists '.$this->params['db_name']);
        mysqli_query($this->db,'create database '.$this->params['db_name']);
    }
    
    /**
     * @When I fill in :arg1 with a valid username
     */
    public function iFillInWithAValidUsername($arg1)
    {
        $this->fillField($arg1, $this->params['admin_username']);
    }
    /**
     * @When I fill in :arg1 with a valid password
     */
    public function iFillInWithAValidPassword($arg1)
    {
        $this->fillField($arg1, $this->params['admin_password']);
    }
    /**
     * @When /^I fill in "([^"]*)" with an email address$/
     */
    public function iFillInWithAnEmailAddress($fieldName)
    {
        $this->data['email'] = 'email@domain.com'; // at some point really make random
        $this->fillField($fieldName, $this->data['email']);
    }
    /**
     * @Given /^I should see the email address I entered$/
     */
    public function iShouldSeeTheEmailAddressIEntered()
    {
        $this->assertSession()->pageTextContains($this->data['email']);
    }
    /**
     * @Given /^No campaigns yet exist$/
     */
    public function iHaveNotYetCreatedCampaigns()
    {
        // Count the number of campaigns in phplist_message table
        $result = mysqli_fetch_assoc(
            mysqli_query(
                $this->db,'
                    select 
                        count(*) as count 
                    from 
                        phplist_message;
                ')
        );
        $campaignCount = $result['count'];
        if ($campaignCount > 0) {
            $this->throwExpectationException('One or more campagins already exist');
        }
    }
    /**
     * @Given /^I have logged in as an administrator$/
     */
    public function iAmAuthenticatedAsAdmin() {
        $this->visit('/lists/admin/');
        $this->fillField('login', $this->params['admin_username']);
        $this->fillField('password', $this->params['admin_password']);
        $this->pressButton('Continue');
        
        if (null === $this->getSession ()->getPage ()->find ('named', array('content', 'Dashboard'))) {
            $this->throwExpectationException('Login failed: Dashboard link not found');
        }
    }
   /**
     * @When I switch to iframe :arg1
     */
    public function iSwitchToIframe($arg1)
    {  $arg1=$this->find("css",'cke_wysiwyg_frame cke_reset');
        $this->getSession()->switchToIFrame($arg1);
       
    }
    
    /**
     * Go back to main document frame.
     *
     * @When (I )switch to main frame
     */
    public function switchToMainFrame()
    {
        $this->getSession()->getDriver()->switchToDefaultContent(); 
    }

    /**
     * @Then I click on :arg1
     */
    public function iClickOn($arg1)
    {  $arg1= $this->find("css",'submit btn btn-primary');
       $this->getSession()->click($arg1);
    }
     /**
     * @When I enter text :arg1
     */
    public function iEnterText($arg1)
    { 

        $script = <<<JS
            (function(){
        CKEDITOR.instances.message.setData( '<p>This is the editor data.</p>' ); })();
JS;
    //$this->getSession()->executeScript("document.body.innerHTML = '<p>".$arg1."</p>'");}
      $this->getSession()->evaluateScript($script);
    }
      /**
     * @Then I should read :arg1
     */
    public function iShouldRead($arg1)
    {
        $script = <<<JS
        (function(){
            CKEDITOR.instances.message.getData();})();

JS;
  $this->getSession()->evaluateScript($script);
    }
       /**
     * @Then :arg1 checkbox should be checked
     */

   /**
    * @Then /^Radio button with id "([^"]*)" should be checked$/
    */
   public function RadioButtonWithIdShouldBeChecked($sId)
   {
       $elementByCss = $this->getSession()->getPage()->find('css', 'input[type="radio"]:checked#'.$sId);
       if (!$elementByCss) {
           throw new Exception('Radio button with id ' . $sId.' is not checked');
       }
   }

       /**
     * @When I switch back from iframe
     */
    public function iSwitchBackFrom($name=null)
    {
     $this->getSession()->getDriver()->switchToIframe(null);
    }

      /**
     * @Then I switch to other iframe :arg1
     */
    public function iSwitchToOtherIframe($arg1)
    {
      $this->getSession()->switchToIframe($arg1);
    }
    
    /**
     * @Given I mouse over :arg1
     */
    public function iMouseOver($arg1)
    {
         $page = $this->getSession()->getPage();
    $findName = $page->find("xpath", '//*[@id="menuTop"]/ul[5]/li');
    if (!$findName) {
        throw new Exception($arg1 . " could not be found");
    } else {
        $findName->mouseOver();
    }
}
     /**
     * @Given I click over :arg1
     */
    public function iClickOver($arg1)
    {
         $page = $this->getSession()->getPage();
    $findName = $page->find("xpath", '//*[@id="wrapp"]/form/div[1]/div/span[1]/a');
        $findName->click();
    }

    /**
   * @Given I write :text into :field
   */
  public function iWriteTextIntoField($text, $field)
  {
    $field = $this->getSession()
      ->getDriver()
      ->getWebDriverSession()
      ->element('xpath', '//*[@id="edit_list_categories"]/div/input');
      $field->postValue(['value' => [$text]]);
  }


       /**
     * @Given I go back
     */
    public function iGoBack()
    {
        $this->getSession()->getDriver()->back();
    }
 /**
     * @Given I go back to :arg1
     */
    public function iGoBackTo($page)
    {
        $this->getSession()->getDriver()->back();
    }

 /**
     * @Then The header color should be black
     */
    public function theDivContextMenuBlockMenuColorShouldBeBlack()
    {

        // JS script that makes the CSS assertion in the browser.

        $script = <<<JS
            (function(){
                return $('#header').css('color') === 'rgb(51, 51, 51)';
            })();
JS;

        if (!$this->getSession()->evaluateScript($script)) {
            throw new Exception();
        }
    }
          /**
     * @Then I should see :message on popups
     */
    public function iShouldSeeOnPopups($message)
    {   return $message == $this->getSession()->getDriver()->getWebDriverSession()->getAlert_text();
    
    }
     /**
     * @When I confirm the popup
     */
    public function iConfirmPopup()
    {  
    $this->getSession()->getDriver()->getWebDriverSession()->accept_alert();
    }
}
