# A Good Day to Make RPG Game
## Github
* https://github.com/MoonScenty/RPG.git
* 커밋 메시지에 Co-Authored-By: Claude 같은 AI 도구 공동저자 트레일러를 절대 추가하지 않는다.
* 커밋 메시지는 한국어로 작성한다.

## 프로젝트 명
* 영문 : A Good Day to Make RPG Game
* 한글 : RPG 게임 만들기 좋은 날

## 프로젝트 상세
* 웹게임
* 배틀 시스템은 ATB Side View Battle System(Full Auto)

## 폴더 구조
* FrontEnd
* BackEnd
* RPGEditor
* RPGProject

## FrontEnd 및 BackEnd
* 예전 Hall of Avarice 에서 가져와서 진행할 예정(지금 구현 해야 하는것은 아님)

## RPGEditor
* C# .NET 10.0 + WPF Visual Studio Project(지금 구현 해야 하는것)

### RPGEditor 상세
* 기존의 RPG Maker MZ로 기본 데이터를 만들고 Seed하는 부분에서 구현에 필요한 부분은 많으나 해당 기능이 RPG Maker MZ에는 없는 기능들이 너무 많았음
* RPGEditor라고 해서 JSON 파일들을 직접 관리할 수 있는 프로그램을 만들어두면 나중에 확장에 더 편하게 웹게임을 유지 보수할 수 있을거라고 생각됨
* 프로젝트 파일  (ProjectName).rpgprj
* 프로젝트 구조
  * audio
    * bgm
    * bgs
    * me
    * se
  * data
    * Actors.json
    * Animations.json
    * Items.json
    * Classes.json
    * Enemies.json
    * Skills.json
    * States.json
    * System.json
    * Troops.json
  * effects
    * Model
    * Texture
    * efkefc files..
  * img
    * battleback1
    * battleback2
    * magic_circles
    * dragonbones
    * faces
    * pictures
    * sv_actors
    * sv_enemies
    * system
  * RPGProject.rpgprj(해당 파일엔 data들이 링크되어 있음, json타입)
* 메인 화면
  * 탭
    * 액터
    * 직업      
    * 스킬
    * 아이템(여기에 무기/방어구도 포함시킴, MZ랑 다른점)
    * 적 캐릭터
    * 적 군단
    * 상태(디버프, 버프 관리)
    * 애니메이션
    * 시스템
    * 유형
