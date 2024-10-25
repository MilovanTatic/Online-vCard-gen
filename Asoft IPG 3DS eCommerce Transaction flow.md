# 3DS E-commerce Transactions ​

## Introduction

This section describes the stages of an e-commerce transaction using the IPG platform and HPP web interface, focusing on the actions carried out by each party involved. ​

### The Buyer Perspective ​

1. Chooses products.  
2. Enters personal details for shipment and clicks "Buy". ​  
3. Is redirected to the HPP.  
4. Enters credit card data and clicks "Pay".  
5. If the card is 3-D Secure enabled, the Buyer is redirected to their bank's website to enter the password and then returns to the HPP. ​  
6. Is redirected to a specific page on the Merchant website displaying the payment result. ​  
7. Receives an email notification of payment if enabled by the Merchant. ​

### The Merchant Perspective ​

1. Receives a purchase order from the Buyer. ​  
2. Sends a PaymentInit message to IPG. ​  
3. Receives a unique PaymentID and the URL of the HPP. ​  
4. Redirects the Buyer to the HPP URL with the PaymentID. ​  
5. Receives a transaction notification from IPG. ​  
6. Responds with the URL for the Buyer to be redirected to for the transaction result. ​  
7. Presents the result to the Buyer. ​  
8. Receives an email notification of payment if enabled. ​

### The IPG Perspective ​

1. Receives a PaymentInit message from the Merchant. ​  
2. Responds with the HPP URL and a PaymentID. ​  
3. Presents the HPP to the Buyer. ​  
4. Receives the Buyer's credit card data. ​  
5. If the card is 3-D Secure enabled, redirects the Buyer to the bank's site for authentication and awaits the result. ​  
6. Processes the transaction by sending the request to the credit card company and gets a response. ​  
7. Sends a result notification message to the Merchant. ​  
8. Receives the URL for Buyer redirection. ​  
9. Redirects the Buyer to the specified URL. ​  
10. Sends an email notification of payment to the Buyer and/or Merchant if enabled. ​

### Diagram of Information Flow ​

The following pattern of actions/communications occurs during a transaction:

1. Buyer completes the shopping cart. ​  
2. Merchant prepares and returns the checkout page. ​  
3. Buyer fills out required fields and clicks "Buy".  
4. Merchant sends PaymentInit request to IPG. ​  
5. IPG verifies the request, saves transaction data, and returns the HPP URL and PaymentID. ​  
6. Merchant saves the PaymentID and redirects the browser to the HPP URL with the PaymentID. ​  
7. IPG checks the PaymentID, prepares the payment page, and returns it to the Buyer's browser. ​  
8. Buyer enters necessary data and clicks "Pay".  
9. If 3-D Secure, IPG redirects the browser to the bank's site for authentication. ​  
10. Buyer provides authentication data and is redirected back to IPG.  
11. IPG combines data and sends the request to the authorization system. ​  
12. Authorization system processes the request and returns the result to IPG. ​  
13. IPG sends a POST message to the Merchant with the transaction result. ​  
14. Merchant updates the transaction status and returns the URL for Buyer redirection. ​  
15. IPG redirects the browser to the specified URL and displays the final page with payment details. ​  
16. Buyer reviews the Merchant result page. ​

### Description of the Steps ​

The table below presents the full flow of activities in a payment transaction:

| Buyer | Merchant | Website IPG | Authorization Centre |
| ----- | ----- | ----- | ----- |
| 1\. Completes Shopping Cart. ​ | 2\. Prepares and returns the Check Out page. ​ |  |  |
| 3\. Fills out the required fields and clicks the "Buy” button. ​ | 4\. Prepares the HTTP PaymentInit request with all transaction data and sends it via POST to IPG. ​ | 5\. After verifying the validity of the request received, IPG saves the transaction data, associates a PaymentID to it and returns to the Merchant the URL where the Cardholder browser must be redirected and the PaymentID to use in redirection. ​ |  |
|  | 6\. Saves the PaymentID among other transaction data, then redirects the browser to the URL of the HPP specifying the PaymentID as the GET parameter. ​ | 7\. After checking the PaymentID received, IPG prepares the payment page and returns it to the buyer‘s browser. ​ |  |
| 8\. Enters the necessary data, and clicks the "Pay" button. Note: If the Buyer clicks the "Cancel" button, the transaction is not processed, and the flow proceeds to step 13\. ​ |  | 9\. (if the card is enabled for 3-D Secure) Redirects the browser to an external site to authenticate the Cardholder. ​ |  |
| 10\. (if the card is enabled for 3-D Secure) Provides their authentication data to the external site (the site of the bank that issued the credit card) and, at the end, is redirected to IPG. ​ |  | 11\. Receives data, combines it with data from the Merchant and the transaction and sends the request to the Authorisation System. ​ | 12\. Receives and processes the request and returns the result to IPG. ​ |
|  |  | 13\. Sends a POST message to the Merchant communicating the result of the transaction. ​ |  |
|  | 14\. Receives the message and updates the transaction status with the result received. ​ It then returns the URL where the buyer browser is to be redirected to for the presentation of the response page. ​ |  |  |
|  |  | 15\. Redirects the buyer browser to the URL specified by the merchant in previous step and displays the final page, with details of the payment result. ​ |  |
| 16\. Receives and reviews Merchant result page. ​ |  |  |  |

## Merchant Integration

### Introduction

IPG includes direct communications with the Merchant server to complete transactions. ​ This can be implemented via:

* A special plug-in. ​  
* Creating a custom communication interface. ​

### Messages Between the Merchant Site and IPG ​

Server-to-server messages are divided into:

* Online messages: Occur during the transaction and are mandatory.  
* Offline messages: Occur after the transaction and are optional. ​

### Online Messages

* PaymentInit Request: Sent by the Merchant to IPG to initialize the transaction. ​  
* PaymentInit Response: Sent by IPG to the Merchant containing the HPP URL and PaymentID. ​  
* Notification: Sent by IPG to the Merchant with transaction results. ​  
* Notification Response: Sent by the Merchant to IPG with the final redirection URL. ​

### Offline Messages

* Payment Request: Used for various accounting transactions post-payment. ​  
* PaymentQuery: Allows the Merchant to check the status and details of a transaction in real-time. ​

### Message Verifier

All messages are signed using a Message Verifier (msgVerifier) generated by:

1. Concatenating specified message data. ​  
2. Removing spaces.  
3. Hashing the string using SHA256. ​  
4. Base64 encoding the hash bytes. ​

Example for PaymentInit request:

* Concatenate: msgName \+ version \+ id \+ password \+ amt \+ trackid \+ udf1 \+ SECRET KEY \+ udf5 ​  
* Remove spaces.  
* Hash using SHA256. ​  
* Base64

