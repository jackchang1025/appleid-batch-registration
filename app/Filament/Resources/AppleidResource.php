<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppleidResource\Pages;
use App\Models\Appleid;
use App\Models\Email;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Enums\EmailStatus;
class AppleidResource extends Resource
{
    protected static ?string $model = Appleid::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $label = 'appleid 管理';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\Select::make('emails')
                    ->label('选择邮箱')
                    ->options(Email::whereIn('status', [EmailStatus::AVAILABLE, EmailStatus::FAILED])->pluck('email', 'email'))
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('选择需要注册 Apple ID 的邮箱'),

                Forms\Components\Select::make('country')
                    ->label('国家')
                    ->required()
                    ->searchable()
                    ->options([
                        'USA' => '美国',
                        'CAN' => '加拿大',
                        'GBR' => '英国',
                        'AUS' => '澳大利亚',
                        'NZL' => '新西兰',
                        'DEU' => '德国',
                        'FRA' => '法国',
                        'ITA' => '意大利',
                        'ESP' => '西班牙',
                        'JPN' => '日本',
                        'KOR' => '韩国',
                        'TWN' => '台湾',
                        'HKG' => '香港',
                        'MAC' => '澳门',
                        'CHN' => '中国大陆',

                    ])
                    ->default('USA')
                    ->helperText('选择需要注册 Apple ID 的国家'),



                Forms\Components\CheckboxList::make('emails')
                    ->label('选择邮箱')
                    ->options(Email::whereIn('status', [EmailStatus::AVAILABLE, EmailStatus::FAILED])->pluck('email', 'email'))
                    ->searchable()
                    ->required()
                    ->columns(4)
                    ->gridDirection('row')
                    ->bulkToggleable(),

                    //<select class="form-dropdown-select" id="form-dropdown-1741671635390-2" aria-labelledby="form-dropdown-1741671635390-2_label" name="countrySelect"><option value="AFG">Afghanistan</option><option value="ALA">Åland Islands</option><option value="ALB">Albania</option><option value="DZA">Algeria</option><option value="ASM">American Samoa</option><option value="AND">Andorra</option><option value="AGO">Angola</option><option value="AIA">Anguilla</option><option value="ATA">Antarctica</option><option value="ATG">Antigua And Barbuda</option><option value="ARG">Argentina</option><option value="ARM">Armenia</option><option value="ABW">Aruba</option><option value="AUS">Australia</option><option value="AUT">Austria</option><option value="AZE">Azerbaijan</option><option value="BHS">Bahamas</option><option value="BHR">Bahrain</option><option value="BGD">Bangladesh</option><option value="BRB">Barbados</option><option value="BLR">Belarus</option><option value="BEL">Belgium</option><option value="BLZ">Belize</option><option value="BEN">Benin</option><option value="BMU">Bermuda</option><option value="BTN">Bhutan</option><option value="BOL">Bolivia</option><option value="BIH">Bosnia and Herzegovina</option><option value="BWA">Botswana</option><option value="BVT">Bouvet Island</option><option value="BRA">Brazil</option><option value="VGB">British Virgin Islands</option><option value="BRN">Brunei Darussalam</option><option value="BGR">Bulgaria</option><option value="BFA">Burkina Faso</option><option value="BDI">Burundi</option><option value="KHM">Cambodia</option><option value="CMR">Cameroon</option><option value="CAN">Canada</option><option value="CPV">Cape Verde</option><option value="BES">Caribbean Netherlands</option><option value="CYM">Cayman Islands</option><option value="CAF">Central African Republic</option><option value="TCD">Chad</option><option value="IOT">Chagos Archipelago</option><option value="CHL">Chile</option><option value="CHN">China mainland</option><option value="CXR">Christmas Island</option><option value="CCK">Cocos (Keeling) Islands</option><option value="COL">Colombia</option><option value="COM">Comoros</option><option value="COK">Cook Islands</option><option value="CRI">Costa Rica</option><option value="CIV">Côte d'Ivoire</option><option value="HRV">Croatia</option><option value="CUW">Curaçao</option><option value="CYP">Cyprus</option><option value="CZE">Czechia</option><option value="COD">Democratic Republic of the Congo</option><option value="DNK">Denmark</option><option value="DJI">Djibouti</option><option value="DMA">Dominica</option><option value="DOM">Dominican Republic</option><option value="ECU">Ecuador</option><option value="EGY">Egypt</option><option value="SLV">El Salvador</option><option value="GNQ">Equatorial Guinea</option><option value="ERI">Eritrea</option><option value="EST">Estonia</option><option value="SWZ">Eswatini</option><option value="ETH">Ethiopia</option><option value="FLK">Falkland Islands</option><option value="FRO">Faroe Islands</option><option value="FJI">Fiji</option><option value="FIN">Finland</option><option value="FRA">France</option><option value="GUF">French Guiana</option><option value="PYF">French Polynesia</option><option value="ATF">French Southern Territories</option><option value="GAB">Gabon</option><option value="GMB">Gambia</option><option value="GEO">Georgia</option><option value="DEU">Germany</option><option value="GHA">Ghana</option><option value="GIB">Gibraltar</option><option value="GRC">Greece</option><option value="GRL">Greenland</option><option value="GRD">Grenada</option><option value="GLP">Guadeloupe</option><option value="GUM">Guam</option><option value="GTM">Guatemala</option><option value="GGY">Guernsey</option><option value="GIN">Guinea</option><option value="GNB">Guinea-Bissau</option><option value="GUY">Guyana</option><option value="HTI">Haiti</option><option value="HMD">Heard And Mc Donald Islands</option><option value="HND">Honduras</option><option value="HKG">Hong Kong</option><option value="HUN">Hungary</option><option value="ISL">Iceland</option><option value="IND">India</option><option value="IDN">Indonesia</option><option value="IRQ">Iraq</option><option value="IRL">Ireland</option><option value="IMN">Isle of Man</option><option value="ISR">Israel</option><option value="ITA">Italy</option><option value="JAM">Jamaica</option><option value="JPN">Japan</option><option value="JEY">Jersey</option><option value="JOR">Jordan</option><option value="KAZ">Kazakhstan</option><option value="KEN">Kenya</option><option value="KIR">Kiribati</option><option value="XKS">Kosovo</option><option value="KWT">Kuwait</option><option value="KGZ">Kyrgyzstan</option><option value="LAO">Laos</option><option value="LVA">Latvia</option><option value="LBN">Lebanon</option><option value="LSO">Lesotho</option><option value="LBR">Liberia</option><option value="LBY">Libya</option><option value="LIE">Liechtenstein</option><option value="LTU">Lithuania</option><option value="LUX">Luxembourg</option><option value="MAC">Macao</option><option value="MDG">Madagascar</option><option value="MWI">Malawi</option><option value="MYS">Malaysia</option><option value="MDV">Maldives</option><option value="MLI">Mali</option><option value="MLT">Malta</option><option value="MHL">Marshall Islands</option><option value="MTQ">Martinique</option><option value="MRT">Mauritania</option><option value="MUS">Mauritius</option><option value="MYT">Mayotte</option><option value="MEX">Mexico</option><option value="FSM">Micronesia</option><option value="MDA">Moldova</option><option value="MCO">Monaco</option><option value="MNG">Mongolia</option><option value="MNE">Montenegro</option><option value="MSR">Montserrat</option><option value="MAR">Morocco</option><option value="MOZ">Mozambique</option><option value="MMR">Myanmar</option><option value="NAM">Namibia</option><option value="NRU">Nauru</option><option value="NPL">Nepal</option><option value="NLD">Netherlands</option><option value="NCL">New Caledonia</option><option value="NZL">New Zealand</option><option value="NIC">Nicaragua</option><option value="NER">Niger</option><option value="NGA">Nigeria</option><option value="NIU">Niue</option><option value="NFK">Norfolk Island</option><option value="MNP">Northern Mariana Islands</option><option value="MKD">North Macedonia</option><option value="NOR">Norway</option><option value="OMN">Oman</option><option value="PAK">Pakistan</option><option value="PLW">Palau</option><option value="PSE">Palestinian Territories</option><option value="PAN">Panama</option><option value="PNG">Papua New Guinea</option><option value="PRY">Paraguay</option><option value="PER">Peru</option><option value="PHL">Philippines</option><option value="PCN">Pitcairn</option><option value="POL">Poland</option><option value="PRT">Portugal</option><option value="PRI">Puerto Rico</option><option value="QAT">Qatar</option><option value="COG">Republic of the Congo</option><option value="REU">Réunion</option><option value="ROU">Romania</option><option value="RUS">Russia</option><option value="RWA">Rwanda</option><option value="BLM">Saint Barthélemy</option><option value="SHN">Saint Helena</option><option value="KNA">Saint Kitts And Nevis</option><option value="LCA">Saint Lucia</option><option value="MAF">Saint Martin</option><option value="VCT">Saint Vincent and the Grenadines</option><option value="WSM">Samoa</option><option value="SMR">San Marino</option><option value="STP">Sao Tome And Principe</option><option value="SAU">Saudi Arabia</option><option value="SEN">Senegal</option><option value="SRB">Serbia</option><option value="SYC">Seychelles</option><option value="SLE">Sierra Leone</option><option value="SGP">Singapore</option><option value="SXM">Sint Maarten</option><option value="SVK">Slovakia</option><option value="SVN">Slovenia</option><option value="SLB">Solomon Islands</option><option value="SOM">Somalia</option><option value="ZAF">South Africa</option><option value="SGS">South Georgia and South Sandwich Islands</option><option value="KOR">South Korea</option><option value="SSD">South Sudan</option><option value="ESP">Spain</option><option value="LKA">Sri Lanka</option><option value="SPM">St. Pierre And Miquelon</option><option value="SDN">Sudan</option><option value="SUR">Suriname</option><option value="SJM">Svalbard And Jan Mayen Islands</option><option value="SWE">Sweden</option><option value="CHE">Switzerland</option><option value="TWN">Taiwan</option><option value="TJK">Tajikistan</option><option value="TZA">Tanzania</option><option value="THA">Thailand</option><option value="TLS">Timor-Leste</option><option value="TGO">Togo</option><option value="TKL">Tokelau</option><option value="TON">Tonga</option><option value="TTO">Trinidad and Tobago</option><option value="TUN">Tunisia</option><option value="TUR">Türkiye</option><option value="TKM">Turkmenistan</option><option value="TCA">Turks and Caicos Islands</option><option value="TUV">Tuvalu</option><option value="UGA">Uganda</option><option value="UKR">Ukraine</option><option value="ARE">United Arab Emirates</option><option value="GBR">United Kingdom</option><option value="USA">United States</option><option value="UMI">United States Minor Outlying Islands</option><option value="URY">Uruguay</option><option value="UZB">Uzbekistan</option><option value="VUT">Vanuatu</option><option value="VAT">Vatican</option><option value="VEN">Venezuela</option><option value="VNM">Vietnam</option><option value="VIR">Virgin Islands (U.S.)</option><option value="WLF">Wallis And Futuna Islands</option><option value="ESH">Western Sahara</option><option value="YEM">Yemen</option><option value="ZMB">Zambia</option><option value="ZWE">Zimbabwe</option></select>

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('email'),
                Tables\Columns\TextColumn::make('email_uri'),
                Tables\Columns\TextColumn::make('password'),
                Tables\Columns\TextColumn::make('phone'),
                Tables\Columns\TextColumn::make('phone_rui'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppleids::route('/'),
            'create' => Pages\CreateAppleid::route('/create'),
//            'edit' => Pages\EditAppleid::route('/{record}/edit'),
        ];
    }
}
