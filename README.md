User account l'éléfàn
========================
modèle de données

![modele](https://yuml.me/306688e0.svg)
![modele](https://yuml.me/306688e0.jpg)

<code>
[FOS User|username;password]<1-1<>[User|member_number]
[User]++1-1..*<>[Beneficiary|is_main;lastname;firstname;phone;email]
[User]++-1<>[Address|street;zip;city]
[User]<*-*>[Commission|name],[User]<2-++[Commission],[user]1-*++[Registration|date;amount;mode]</code>