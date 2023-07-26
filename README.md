# HuwelijksplannerBundle [![Codacy Badge](https://app.codacy.com/project/badge/Grade/a86fa955b62542e4a2e9b88d9ee618d4)](https://app.codacy.com/gh/CommonGateway/HuwelijksplannerBundle/dashboard?utm_source=gh\&utm_medium=referral\&utm_content=\&utm_campaign=Badge_grade)

The HuwelijksplannerBundle is a powerful Symfony bundle that provides essential functionality for handling Dutch marriage requests within municipalities. This bundle is specifically designed to streamline and simplify the process of managing marriage-related operations, making it an indispensable tool for municipal authorities.

## Changes from Huwelijksplanner 2020

In the previous iteration of the Huwelijksplanner project, the decision was made to fully separate the Huwelijksplanner into individual registers, each developed as a separate Common Ground component. These components were then required to be installed independently.

However, this approach led to several issues. Numerous installations were necessary to get the Huwelijksplanner up and running, and the large number of codebases posed maintenance challenges.

In the current iteration of the Huwelijksplanner, we have addressed these concerns by reorganizing the individual components as plugins. This change offers two primary advantages:

1.  Smaller Codebases: By restructuring the components into plugins, the underlying codebases are now more manageable and easier to maintain.

2.  Unified Gateway Installation: These different plugins can now be run on a single installation of the Common Gateway. This streamlines the deployment process and simplifies the setup for developers.

Optionally, municipalities still have the flexibility to choose between running the components in separate installations or combining them in a unified setup.

### Bundles Used in the HuwelijksplannerBundle

The [**HuwelijksplannerBundle**](https://github.com/CommonGateway/HuwelijksplannerBundle) currently utilizes the following bundles:

1.  **CoreBundle**: [GitHub Repository](https://github.com/CommonGateway/CoreBundle)
2.  **BRPBundle**: [GitHub Repository](https://github.com/CommonGateway/BRPBundle)
3.  **KlantenBundle**: [GitHub Repository](https://github.com/CommonGateway/KlantenBundle)
4.  **ZGWBundle**: [GitHub Repository](https://github.com/CommonGateway/ZGWBundle)

While all these bundles can still be installed as standalone components (please refer to their respective installation guides), the HuwelijksplannerBundle now defaults to installing these bundles as additional plugins on the same gateway.

This new approach offers greater modularity, making it easier for developers to work with the HuwelijksplannerBundle and allowing for more flexible configurations based on the needs of individual municipalities.

If you have any questions, suggestions, or new ideas, please don't hesitate to share them in the project repository. Let's work together to create an exceptional wedding planning experience that brings joy and happiness to couples preparing for their special day.

## Backend Installation Instructions

The Huwelijksplanner backend codebase utilizes the Common Gateway as an open-source installation framework. This means that the Huwelijksplanner library, in its core form, functions as a plugin on this Framework. To learn more about the Common Gateway, you can refer to the documentation [here](https://commongateway.readthedocs.io/en/latest/).

Please note that the Huwelijksplanner frontend codebase is a separate docker container.

To install the backend, follow the steps below:

### Gateway Installation

1.  If you do not have the Common Gateway installed, you can follow the installation guide provided [here](https://github.com/ConductionNL/commonground-gateway#readme). The Common Gateway installation is required for the backend setup. You can choose any installation method for the gateway, such as Haven, Kubernetes, Linux, or Azure, and any database option like MySQL, PostgreSQL, Oracle, or MsSQL. The gateway framework handles this abstraction.

### HuwelijksplannerBundle Installation - Admin-UI

1.  After successfully installing the Gateway, access the admin-ui and log in.
2.  In the left menu, navigate to "Plugins" to view a list of installed plugins. If you don't find the "Huwelijksplanner" plugin listed here, you can search for it by clicking on "Search" in the upper-right corner and typing "Huwelijksplanner" in the search bar.
3.  Click on the "Huwelijksplanner" card and then click on the "Install" button to install the plugin.
4.  The admin-ui allows you to install, upgrade, or remove bundles. However, to load all the required data (schemas, endpoints, sources), you need to execute the initialization command in a terminal.

### HuwelijksplannerBundle Installation - Terminal

1.  Open a terminal and run the following command to install the Huwelijksplanner bundle:

        docker-compose exec php composer require common-gateway/huwelijksplanner-bundle

### Initialization Command (Terminal)

1.  To load all the data without any specific content, execute the following command:

        docker-compose exec php bin/console commongateway:initialize

    OR

    To load all the data along with specific content, run:

        docker-compose exec php bin/console commongateway:initialize -data

With these steps completed, the backend setup for the Huwelijksplanner project should be ready to use. If you encounter any issues during the installation process, seek assistance from the development team. Happy coding!

## Frontend Installation Instructions

These instructions will guide you through the setup process for the Huwelijksplanner project. Please follow the steps below to get started:

### Prerequisites

Before you begin, ensure you have the following software installed on your system:

1.  Git
2.  Node.js (npm)
3.  Docker
4.  Docker Compose

### Installation

1.  Clone the Utrecht Huwelijksplanner repository by running the following command in your terminal:

        git clone https://github.com/frameless/utrecht-huwelijksplanner.git

2.  In the project directory, find the `.env` file and modify the `NEXT_PUBLIC_API_URL` to use the local API. Change it to:

        NEXT_PUBLIC_API_URL=http://localhost/api

3.  Run the following command in the terminal to install the project dependencies and generate necessary code:

        npm install && npm run codegen

4.  Build and start the development environment using Docker Compose:

        docker-compose -f docker-compose.dev.yml build
        docker-compose -f docker-compose.dev.yml up --remove-orphans

    If you encounter an error during this step, try running the production Docker Compose file located in the repository under `Docker-compose.yml`. After a successful build, you can retry step 4.


With these steps completed, the frontend setup for the Huwelijksplanner project should be ready to use. If you encounter any issues during the installation process, seek assistance from the development team. Happy coding!

## Admin UI - Setup Instructions

Once the backend (and frontend) is up and running, the HuwelijksplannerBundle can be configured. To ensure proper functionality, the sources and Security Group (Default Anonymous user) need to be modified. Other adjustments are optional.

### Configuration Steps:

1. **Users**
    - Change the passwords of the users if necessary.
      - Go to `Settings` in the Admin UI. 
      - Navigate to the `Users` tab.
      - Select the user and edit the password.

2. **Security Group**
    - Add the scopes for the Default Anonymous in the Security Group.
      - Go to `Settings` in the Admin UI.
      - Navigate to the `Security Groups` tab
      - Locate and select `Default Anonymous` to view its details
      - Add the following scopes under the `Scopes` section:
          ```
          - schemas.https://huwelijksplanner.nl/schemas/hp.availability.schema.json.GET
          - schemas.https://huwelijksplanner.nl/schemas/hp.sdgProduct.schema.json.GET
          ```

3. **Sources**
    - Provide the required API keys for the following sources:
        - SendInBlue API
        - Mollie API
        - MessageBird API

4. **Actions**
    - Change the sender of the SMS in the `MessageBird` action:
      - Go to `Actions` in the Admin UI. 
      - Locate and select `MessageBird` to view its details
      - Set the `Originator` to the sender of the SMS.

5. **Mappings**
    - Configure SMS and Email data for the partner and/or the witnesses:
      - Go to `Mappings` in the Admin UI.
      - Locate and select `EmailAndSmsDataPartner` or `EmailAndSmsDataWitness` to view its details
      - Change the values of body, assentName, assentDescription and url.
        - `body` is the body of the sms
        - `assentName` is the name of the assent that is made for this partner
        - `assentDescription` is the description of the assent that is made for this partner
        - `url` is the url that the partner is directed to, to confirm the marriage

Once you have completed these steps, the Huwelijksplanner Admin UI should be fully configured and is the Huwelijksplanner project ready to use.

To gain a deeper understanding of the services and commands offered by the HuwelijksplannerBundle, we encourage you to explore the detailed documentation available at <https://commongateway.github.io/HuwelijksplannerBundle/>. This documentation provides comprehensive insights into the bundle's capabilities, service usage, and available commands.

Whether you are a developer working on a municipal wedding planning project or an enthusiast seeking to learn more about the HuwelijksplannerBundle, this documentation is an invaluable resource to help you navigate and utilize the bundle effectively.

Join us in harnessing the power of the HuwelijksplannerBundle to create seamless and efficient marriage request handling solutions for Dutch municipalities. Happy coding!
