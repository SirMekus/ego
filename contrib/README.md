## Contributing

Thank you for considering contributing to this project/library. With your support, we can have a single package with over 100+ payment integrations from a single platform. This means less work for developers (as they will only need to worry about getting their authentication credentials :wink:).

Please review the available methods in the interface, implement them and extend the **`Tollgate`** class. That's all. Don't forget to update this documentation as well...and write extensive test cases to make sure all is well.

For instance, to create a new payment gateway API, you should:
- Extend the `TollGate` class
- Implement the classes in the `PaymentGatewayInterface` interface
- Throw a custom error or any of the ones defined in the **'Exceptions'** directory  when necessary.
- Add a new entry required to interact with your class in the **'ego.php'** file in the **config** directory. The user will then have to set up the credentials (or environment variables)

Kindly review any of the current implementations on how to create your own implementation.