{
  inputs = {
    nixpkgs.url = "github:nixos/nixpkgs/nixos-22.11";
    nixpkgs-old.url = "github:nixos/nixpkgs/nixos-22.05"; # Keep it until php74 is no longer needed
    devenv.url = "github:cachix/devenv";
  };
  outputs = { self, nixpkgs, nixpkgs-old, devenv, ... }@ inputs:
    let
      pkgs = import nixpkgs { system = "x86_64-linux"; };
      pkgs-old = import nixpkgs-old { system = "x86_64-linux"; };

      composer = pkgs-old.php74Packages.composer.overrideDerivation (old: rec {
        version = "2.2.17";
        src = pkgs.fetchurl {
          url = "https://getcomposer.org/download/${version}/composer.phar";
          sha256 = "sha256-7ANNPZLJSrY+vfGiv6n140ywiwQ9rpGVjG/LL0cXDOo=";
        };
      });
    in
    {
      devShells.x86_64-linux.default = devenv.lib.mkShell
        {
          inherit inputs pkgs;
          modules = [
            {
              packages = with pkgs; [ mailcatcher ];

              env = {
                DEV_MODE_ENABLED = "";
              };

              languages.php = {
                enable = true;
                package = pkgs-old.php74;
                packages.composer = composer;
              };

              services.mysql = {
                enable = true;
                package = pkgs.mariadb_109;

                initialDatabases = [
                  { name = "symfony"; }
                ];

                ensureUsers = [
                  {
                    name = "symfony";
                    ensurePermissions = {
                      "symfony.*" = "ALL PRIVILEGES";
                    };
                    password = "symfony";
                  }
                ];
              };
            }
          ];
        };
    };
}
