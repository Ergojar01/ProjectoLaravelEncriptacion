# Preguntas de sustentación (sección 13 del PDF)

**¿Por qué hash y no cifrado reversible?** El cifrado se puede revertir con la clave: quien robe la base y la clave lee todas las contraseñas. El hash es de una sola vía: el sistema nunca necesita la contraseña original, solo comprobar que quien entra conoce una que produce el mismo hash. Además Argon2id es deliberadamente lento y consume memoria, lo que encarece los ataques de fuerza bruta.

**¿Cómo funciona la sal?** Es un valor aleatorio único por contraseña que se mezcla antes de hashear y viaja dentro del hash (`$argon2id$...$sal$hash`). Dos usuarios con la misma contraseña obtienen hashes distintos, y las tablas arcoíris precalculadas dejan de servir. Por eso se verifica con `Hash::check` y no recalculando y comparando texto. En este proyecto se añade además una *pimienta* secreta fuera de la BD.

**Autenticación vs. autorización.** Autenticar es comprobar *quién eres* (usuario + contraseña). Autorizar es decidir *qué puedes hacer* (niveles 500/499/300 aplicados con middleware en cada solicitud).

**¿Por qué ocultar una URL no es seguridad?** Ocultar es oscuridad, no control: la URL se puede adivinar, sacar del código o de las herramientas del navegador. La seguridad está en que cada solicitud se valida en el servidor (sesión, estado de la cuenta, nivel, CSRF), sin importar la dirección visible.

**¿Por qué el administrador no conoce las contraseñas?** Porque solo existen los hashes. Para recuperar una clave, el 500 *autoriza* y la propia persona crea la nueva con un enlace de un solo uso que vence.

**¿Por qué el bloqueo está en MySQL y no en la sesión?** Porque la sesión la controla el atacante: abrir otro navegador la reiniciaría. En la base de datos el bloqueo persiste y se combina cuenta + IP para que un tercero no pueda dejar fuera a un usuario legítimo con solo 3 intentos.
